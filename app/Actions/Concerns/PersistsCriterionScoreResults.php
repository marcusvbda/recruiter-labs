<?php

namespace App\Actions\Concerns;

use App\Actions\ReplaceApplicationFitAnalysis;
use App\Actions\ReplaceSourcingMatchAnalysis;
use App\Enums\AnalysisConfidence;
use App\Enums\CriterionEvidenceSource;
use App\Models\Job;
use App\Models\JobCriterion;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The rules that turn a model's criterion-by-criterion answer into figures the
 * recruiter is shown as fact.
 *
 * Two actions persist such an answer — {@see ReplaceApplicationFitAnalysis} for
 * one application, {@see ReplaceSourcingMatchAnalysis} for one sourced candidate
 * — and they must judge it identically. Response validation, criteria-revision
 * re-checking, authoritative criterion resolution by ID, the unknown-is-not-zero
 * handling and the fit/coverage formulas therefore live here once: two copies of
 * these rules would eventually disagree about what the product knows, and the
 * surface that drifted would be the one making the stronger claim.
 */
trait PersistsCriterionScoreResults
{
    /**
     * The shape a criterion-score response must have. Bounds mirror the agents'
     * schemas, because a provider that ignores its schema must not be able to
     * widen what gets stored.
     *
     * @return array<string, list<string>>
     */
    protected function criterionScoreRules(): array
    {
        return [
            'scores' => ['required', 'array', 'min:1', 'max:20'],
            'scores.*' => ['required', 'array:criterion_id,score,reason,confidence,evidence'],
            'scores.*.criterion_id' => ['required', 'integer'],
            'scores.*.score' => ['present', 'nullable', 'integer', 'between:0,100'],
            'scores.*.reason' => ['required', 'string', 'max:220'],
            'scores.*.confidence' => ['required', 'string', 'in:high,medium,low'],
            'scores.*.evidence' => ['present', 'array', 'max:3'],
            ...$this->evidenceItemRules(),
        ];
    }

    /**
     * The shape of one evidence item, deliberately overridable.
     *
     * The two callers ask about different material and their evidence is not the
     * same thing. An application's evidence comes from one submission, so naming
     * its source is enough to place it. A sourced candidate's evidence is drawn
     * from everything they ever submitted, where several items can share a
     * source — two application answers from two jobs, resumes uploaded years
     * apart — so the source alone cannot say *which* one, and therefore cannot
     * say when it was written. Sourcing adds a material index for that, and the
     * application side must not inherit a field it has no material to index.
     *
     * @return array<string, list<string>>
     */
    protected function evidenceItemRules(): array
    {
        return [
            'scores.*.evidence.*' => ['required', 'array:source,detail'],
            'scores.*.evidence.*.source' => ['required', 'string', 'in:'.implode(',', CriterionEvidenceSource::values())],
            'scores.*.evidence.*.detail' => ['required', 'string', 'max:180'],
        ];
    }

    /**
     * Whether the job still runs on the exact confirmed criteria revision the AI
     * request was built from.
     *
     * All three conditions matter. The revision must be unchanged, the recorded
     * confirmation must still point at it, and the job must still be in a
     * confirmed state at all — editing criteria advances the revision *and*
     * drops the job back to review, and either half on its own would let a stale
     * answer through.
     */
    protected function criteriaRevisionIsStill(Job $job, int $expectedCriteriaGeneration): bool
    {
        return $job->criteria_generation === $expectedCriteriaGeneration
            && $job->criteria_confirmed_generation === $expectedCriteriaGeneration
            && $job->hasConfirmedCriteria();
    }

    /**
     * The job's current criteria, keyed by ID, restricted to the owning company
     * so a cross-tenant ID can never be resolved.
     *
     * @param  Collection<int, JobCriterion>  $jobCriteria
     * @return array<int, JobCriterion>
     */
    protected function authoritativeCriteria(Collection $jobCriteria, int $companyId): array
    {
        $criteria = [];

        foreach ($jobCriteria as $criterion) {
            if ((int) $criterion->company_id !== $companyId) {
                continue;
            }

            $criteria[(int) $criterion->getKey()] = $criterion;
        }

        if ($criteria === []) {
            throw ValidationException::withMessages([
                'scores' => 'The job has no evaluation criteria to score against.',
            ]);
        }

        return $criteria;
    }

    /**
     * Every criterion once, nothing extra, nothing missing. Anything else is a
     * response that cannot be mapped onto the job the recruiter confirmed, and
     * the honest outcome is a failed execution rather than an invented one.
     *
     * @param  array<int, JobCriterion>  $criteria
     * @param  array<int, array<string, mixed>>  $scores
     */
    protected function assertCriteriaMatchExactly(array $criteria, array $scores): void
    {
        $returned = array_map(fn (array $score): int => (int) $score['criterion_id'], $scores);
        $expected = array_keys($criteria);

        $unknown = array_values(array_diff($returned, $expected));
        $missing = array_values(array_diff($expected, $returned));
        $duplicated = count($returned) !== count(array_unique($returned));

        if ($unknown !== [] || $missing !== [] || $duplicated) {
            throw ValidationException::withMessages([
                'scores' => 'The evaluation must return each of the job\'s criteria exactly once, by criterion ID.',
            ]);
        }
    }

    /**
     * The rows to store, one per criterion, with the criterion text and weight
     * taken from the authoritative record rather than from whatever the model
     * echoed back.
     *
     * @param  array<int, array<string, mixed>>  $scores
     * @param  array<int, JobCriterion>  $criteria
     * @param  (Closure(array<string, mixed>): array<string, mixed>)|null  $evidenceItem  How one evidence item is stored, when the caller records more provenance than source and detail.
     * @return list<array<string, mixed>>
     */
    protected function criterionScoreRows(array $scores, array $criteria, int $companyId, ?Closure $evidenceItem = null): array
    {
        return array_values(array_map(function (array $result) use ($criteria, $companyId, $evidenceItem): array {
            $criterion = $criteria[(int) $result['criterion_id']];
            $score = $result['score'] === null ? null : (int) $result['score'];

            return [
                'company_id' => $companyId,
                // Snapshot of the confirmed criterion, resolved by ID.
                'criterion' => $criterion->criterion,
                'weight' => $criterion->weight,
                'score' => $score,
                'reason' => $result['reason'],
                'evidence' => $this->evidenceFor($result['evidence'], $score, $evidenceItem),
                'confidence' => $this->confidenceFor($result['confidence'], $score),
            ];
        }, $scores));
    }

    /**
     * Evidence is dropped when the criterion could not be assessed at all: an
     * unassessed criterion citing supporting evidence contradicts itself, and
     * the reason field is where the missing information is explained.
     *
     * @param  array<int, array<string, mixed>>  $evidence
     * @param  (Closure(array<string, mixed>): array<string, mixed>)|null  $evidenceItem
     * @return list<array<string, mixed>>|null
     */
    protected function evidenceFor(array $evidence, ?int $score, ?Closure $evidenceItem = null): ?array
    {
        if ($score === null || $evidence === []) {
            return null;
        }

        // Rebuilt key by key rather than passed through: only the fields the
        // product understands are stored, so a provider cannot smuggle extra
        // keys into a column the recruiter is shown.
        $evidenceItem ??= fn (array $item): array => [
            'source' => $item['source'],
            'detail' => $item['detail'],
        ];

        return array_values(array_map($evidenceItem, $evidence));
    }

    /**
     * Confidence measures how strongly the submitted material supports the
     * assessment, so "no information at all" cannot be high confidence. Rather
     * than failing an otherwise usable evaluation — or spending another AI call
     * — an unassessed criterion is normalised down to low.
     */
    protected function confidenceFor(string $confidence, ?int $score): string
    {
        return $score === null ? AnalysisConfidence::Low->value : $confidence;
    }

    /**
     * Weighted average across the criteria that could actually be assessed.
     * Unknown criteria are in neither the numerator nor the denominator, so
     * missing information cannot pull a candidate's fit down — that is what
     * evidence coverage is for.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    protected function overallFit(array $rows): ?float
    {
        $assessed = array_values(array_filter($rows, fn (array $row): bool => $row['score'] !== null));

        if ($assessed === []) {
            return null;
        }

        $assessedWeight = $this->sumWeights($assessed);

        if ($this->sumWeights($rows) > 0) {
            // Weights rank the criteria, so a fit built only from zero-weight
            // criteria would describe nothing the job actually cares about.
            return $assessedWeight === 0
                ? null
                : round(array_sum(array_map(
                    fn (array $row): int => (int) $row['score'] * (int) $row['weight'],
                    $assessed,
                )) / $assessedWeight, 2);
        }

        // Every criterion carries zero weight: weighting cannot distinguish
        // them, so each one counts once.
        return round(array_sum(array_map(fn (array $row): int => (int) $row['score'], $assessed)) / count($assessed), 2);
    }

    /**
     * How much of the weighted criteria the supplied material allowed to be
     * assessed at all, 0-100. Separate from fit by design: a candidate can be a
     * strong match on everything that could be checked while most of the profile
     * is still unknown, and the recruiter has to be able to see that.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    protected function evidenceCoverage(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $assessed = array_values(array_filter($rows, fn (array $row): bool => $row['score'] !== null));
        $totalWeight = $this->sumWeights($rows);

        if ($totalWeight > 0) {
            return (int) round($this->sumWeights($assessed) / $totalWeight * 100);
        }

        return (int) round(count($assessed) / count($rows) * 100);
    }

    /** @param  list<array<string, mixed>>  $rows */
    protected function sumWeights(array $rows): int
    {
        return array_sum(array_map(fn (array $row): int => (int) $row['weight'], $rows));
    }
}
