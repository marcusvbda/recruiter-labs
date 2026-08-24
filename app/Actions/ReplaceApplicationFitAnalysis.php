<?php

namespace App\Actions;

use App\Enums\AnalysisConfidence;
use App\Enums\ApplicationAnalysisStatus;
use App\Enums\CriterionEvidenceSource;
use App\Models\Application;
use App\Models\ApplicationCriterionScore;
use App\Models\ApplicationInterviewBriefItem;
use App\Models\JobCriterion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Persists a candidate evaluation, deterministically.
 *
 * Everything the recruiter is shown as fact is computed here, not by the model:
 *
 * - **Criterion identity is an ID, never text.** The model returns
 *   `criterion_id`; the authoritative criterion text and weight are read from
 *   the current {@see JobCriterion}. There is no fallback weight and no
 *   case-insensitive string match, because both silently invent an assessment
 *   for a criterion nobody can point at.
 * - **The response is all-or-nothing.** Every current criterion must appear
 *   exactly once, and nothing else may appear. A structurally inconsistent
 *   response fails so the queue can retry it, rather than producing a partial
 *   evaluation that looks complete.
 * - **Unknown is not zero.** A null score means the application did not support
 *   a judgement. It stays out of the fit average entirely and shows up in
 *   evidence coverage instead.
 * - **Fit and coverage are separate numbers**, and confidence is separate from
 *   both. None of them is folded into a single "adjusted" score.
 */
class ReplaceApplicationFitAnalysis
{
    /**
     * @param  array<int, mixed>  $scores
     * @param  array<int, mixed>  $interviewBriefItems
     */
    public function handle(Application $application, array $scores, array $interviewBriefItems, int $expectedGeneration): bool
    {
        $validated = Validator::make(
            ['scores' => $scores, 'interview_brief_items' => $interviewBriefItems],
            [
                'scores' => ['required', 'array', 'min:1', 'max:20'],
                'scores.*' => ['required', 'array:criterion_id,score,reason,confidence,evidence'],
                'scores.*.criterion_id' => ['required', 'integer'],
                // Nullable on purpose: "not enough information to assess this" is
                // a legitimate answer and must not be coerced into a number.
                'scores.*.score' => ['present', 'nullable', 'integer', 'between:0,100'],
                'scores.*.reason' => ['required', 'string', 'max:220'],
                'scores.*.confidence' => ['required', 'string', 'in:high,medium,low'],
                'scores.*.evidence' => ['present', 'array', 'max:3'],
                'scores.*.evidence.*' => ['required', 'array:source,detail'],
                'scores.*.evidence.*.source' => ['required', 'string', 'in:'.implode(',', CriterionEvidenceSource::values())],
                'scores.*.evidence.*.detail' => ['required', 'string', 'max:180'],
                'interview_brief_items' => ['present', 'array', 'max:6'],
                'interview_brief_items.*' => ['required', 'array:criterion_id,priority,reason,question'],
                'interview_brief_items.*.criterion_id' => ['required', 'integer'],
                'interview_brief_items.*.priority' => ['required', 'string', 'in:high,medium,low'],
                'interview_brief_items.*.reason' => ['required', 'string', 'max:220'],
                'interview_brief_items.*.question' => ['required', 'string', 'max:300'],
            ],
        )->validate();

        return DB::transaction(function () use ($application, $validated, $expectedGeneration): bool {
            $lockedApplication = Application::query()->whereKey($application->getKey())->lockForUpdate()->first();

            if ($lockedApplication === null || $lockedApplication->analysis_generation !== $expectedGeneration) {
                return false;
            }

            $job = $lockedApplication->job()->with('jobCriteria')->firstOrFail();
            $criteria = $this->authoritativeCriteria($job->jobCriteria, (int) $lockedApplication->company_id);

            $this->assertCriteriaMatchExactly($criteria, $validated['scores']);

            // Position is the link between the validated response and the rows
            // that get written, so a job with two identically worded criteria
            // still maps each result to its own criterion.
            $criterionIdOrder = array_map(fn (array $result): int => (int) $result['criterion_id'], $validated['scores']);

            $rows = array_values(array_map(function (array $result) use ($lockedApplication, $criteria): array {
                $criterion = $criteria[(int) $result['criterion_id']];
                $score = $result['score'] === null ? null : (int) $result['score'];

                return [
                    'company_id' => $lockedApplication->company_id,
                    // Snapshot of the confirmed criterion, resolved by ID.
                    'criterion' => $criterion->criterion,
                    'weight' => $criterion->weight,
                    'score' => $score,
                    'reason' => $result['reason'],
                    'evidence' => $this->evidenceFor($result['evidence'], $score),
                    'confidence' => $this->confidenceFor($result['confidence'], $score),
                ];
            }, $validated['scores']));

            ApplicationInterviewBriefItem::query()
                ->where('application_id', $lockedApplication->getKey())
                ->delete();
            $lockedApplication->criterionScores()->delete();
            $createdScores = $lockedApplication->criterionScores()->createMany($rows);

            $this->persistInterviewBrief(
                $lockedApplication,
                $validated['interview_brief_items'],
                array_combine($criterionIdOrder, $createdScores->all()),
            );

            $lockedApplication->forceFill([
                'analysis_status' => ApplicationAnalysisStatus::Completed,
                // The revision this evaluation measured. Without it, a criteria
                // change would leave this fit quietly presenting itself as the
                // current assessment.
                'analysis_criteria_generation' => $job->criteria_generation,
                'analysis_score' => $this->overallFit($rows),
                'analysis_coverage' => $this->evidenceCoverage($rows),
                'analyzed_at' => now(),
            ])->saveQuietly();

            return true;
        });
    }

    /**
     * The job's current criteria, keyed by ID, restricted to the application's
     * own company so a cross-tenant ID can never be resolved.
     *
     * @param  Collection<int, JobCriterion>  $jobCriteria
     * @return array<int, JobCriterion>
     */
    private function authoritativeCriteria(Collection $jobCriteria, int $companyId): array
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
                'scores' => 'The job has no evaluation criteria to score this application against.',
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
    private function assertCriteriaMatchExactly(array $criteria, array $scores): void
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
     * Evidence is dropped when the criterion could not be assessed at all: an
     * unassessed criterion citing supporting evidence contradicts itself, and
     * the reason field is where the missing information is explained.
     *
     * @param  array<int, array{source: string, detail: string}>  $evidence
     * @return list<array{source: string, detail: string}>|null
     */
    private function evidenceFor(array $evidence, ?int $score): ?array
    {
        if ($score === null || $evidence === []) {
            return null;
        }

        return array_values(array_map(fn (array $item): array => [
            'source' => $item['source'],
            'detail' => $item['detail'],
        ], $evidence));
    }

    /**
     * Confidence measures how strongly the submitted material supports the
     * assessment, so "no information at all" cannot be high confidence. Rather
     * than failing an otherwise usable evaluation — or spending another AI call
     * — an unassessed criterion is normalised down to low.
     */
    private function confidenceFor(string $confidence, ?int $score): string
    {
        return $score === null ? AnalysisConfidence::Low->value : $confidence;
    }

    /**
     * Interview-brief items reference a criterion by ID like everything else, and
     * resolve to the criterion score row that was just written so the existing
     * relationship keeps pointing at real evidence.
     *
     * @param  array<int, array<string, mixed>>  $briefItems
     * @param  array<int, ApplicationCriterionScore>  $scoresByCriterionId
     */
    private function persistInterviewBrief(
        Application $application,
        array $briefItems,
        array $scoresByCriterionId,
    ): void {
        $rows = [];
        $now = now();

        foreach ($briefItems as $sortOrder => $briefItem) {
            $criterionScore = $scoresByCriterionId[(int) $briefItem['criterion_id']] ?? null;

            if (! $criterionScore instanceof ApplicationCriterionScore) {
                throw ValidationException::withMessages([
                    'interview_brief_items' => 'Each interview brief item must reference one of the job\'s criteria by ID.',
                ]);
            }

            $rows[] = [
                'company_id' => $application->company_id,
                'application_id' => $application->getKey(),
                'application_criterion_score_id' => $criterionScore->getKey(),
                'criterion' => $criterionScore->criterion,
                'priority' => $briefItem['priority'],
                'reason' => $briefItem['reason'],
                'question' => $briefItem['question'],
                'sort_order' => $sortOrder,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            ApplicationInterviewBriefItem::query()->insert($rows);
        }
    }

    /**
     * Weighted average across the criteria that could actually be assessed.
     * Unknown criteria are in neither the numerator nor the denominator, so
     * missing information cannot pull a candidate's fit down — that is what
     * evidence coverage is for.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function overallFit(array $rows): ?float
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
     * How much of the weighted criteria the supplied application allowed to be
     * assessed at all, 0-100. Separate from fit by design: a candidate can be a
     * strong match on everything that could be checked while most of the profile
     * is still unknown, and the recruiter has to be able to see that.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function evidenceCoverage(array $rows): int
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
    private function sumWeights(array $rows): int
    {
        return array_sum(array_map(fn (array $row): int => (int) $row['weight'], $rows));
    }
}
