<?php

namespace App\Actions;

use App\Actions\Concerns\PersistsCriterionScoreResults;
use App\Data\CandidateSourcingMaterial;
use App\Enums\AnalysisConfidence;
use App\Enums\CriterionEvidenceSource;
use App\Enums\SourcingMatchState;
use App\Models\Candidate;
use App\Models\Job;
use App\Models\SourcingMatch;
use App\Models\SourcingSearch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Persists one candidate's sourcing assessment for one job, deterministically.
 *
 * The candidate-level counterpart of {@see ReplaceApplicationFitAnalysis}, and
 * it shares that action's rules through {@see PersistsCriterionScoreResults}:
 * criterion identity is an ID and never text, criterion IDs prove mapping
 * identity while the criteria generation proves semantic revision identity, the
 * response is all-or-nothing, unknown is not zero, and potential match, evidence
 * coverage and confidence stay three separate figures.
 *
 * Three things are specific to sourcing:
 *
 * - **The recruiter's decision outlives the analysis.** A rerun refreshes the
 *   figures on the existing (job, candidate) row and leaves its `state` exactly
 *   as the recruiter left it. Only a pairing nobody has seen before starts as
 *   {@see SourcingMatchState::Suggested}. Refreshing must never quietly undo a
 *   save, and must never return a dismissed person to the active list — that is
 *   what the explicit restore action is for.
 * - **The search's own progress is not this action's business.** It writes one
 *   match and its criterion rows; the run that called it owns the search status
 *   and the reviewed/matched/insufficient counters, so a partial run cannot end
 *   up looking complete because a single candidate happened to persist.
 * - **Evidence is dated, because it was written for something else.** The
 *   material spans the candidate's whole history, so how old a piece of support
 *   is changes what it is worth. Each stored evidence item therefore carries the
 *   submission date of the material it cites, resolved here from the index the
 *   request addressed that material by — the same list, in the same order — and
 *   never from a date the model repeated back.
 */
class ReplaceSourcingMatchAnalysis
{
    use PersistsCriterionScoreResults;

    /**
     * @param  array<int, mixed>  $scores
     * @param  list<CandidateSourcingMaterial>  $materials  The exact ordered list
     *                                                      the request was built
     *                                                      from, so an evidence
     *                                                      item's `material_index`
     *                                                      resolves to the
     *                                                      material that produced
     *                                                      it.
     * @param  int  $expectedGeneration  The search generation this response was
     *                                   requested for.
     * @param  int  $expectedCriteriaGeneration  The confirmed job criteria
     *                                           revision the request was built
     *                                           from.
     * @param  int  $expectedMaterialsRevision  The candidate's material revision
     *                                          read before their material was
     *                                          gathered.
     * @return SourcingMatch|null The persisted match, or null when the response
     *                            no longer describes the current state: a newer
     *                            search run has started, the criteria revision it
     *                            measured is no longer the confirmed one, or the
     *                            candidate's material changed while it was being
     *                            produced.
     */
    public function handle(
        SourcingSearch $search,
        Candidate $candidate,
        array $scores,
        array $materials,
        int $expectedGeneration,
        int $expectedCriteriaGeneration,
        int $expectedMaterialsRevision,
    ): ?SourcingMatch {
        return DB::transaction(function () use ($search, $candidate, $scores, $materials, $expectedGeneration, $expectedCriteriaGeneration, $expectedMaterialsRevision): ?SourcingMatch {
            $lockedSearch = SourcingSearch::query()->whereKey($search->getKey())->lockForUpdate()->first();

            if ($lockedSearch === null || $lockedSearch->generation !== $expectedGeneration) {
                // A newer search was requested while the provider was working.
                // This answer belongs to a run the recruiter has already
                // replaced, so it is dropped rather than written over a fresher
                // one. Not a failure: retrying would only replay it.
                return null;
            }

            $job = Job::query()
                ->whereKey($lockedSearch->job_id)
                ->lockForUpdate()
                ->firstOrFail();
            $job->load('jobCriteria');

            if (! $this->criteriaRevisionIsStill($job, $expectedCriteriaGeneration)) {
                // The recruiter changed the criteria while the request was
                // running, so this answer measures a revision that no longer
                // governs the job. Nothing is persisted — not the figures, not
                // the criterion rows, not the revision link — and no match is
                // created, because a match carrying the wrong revision would
                // present itself in the workspace as an assessment of criteria
                // nobody confirmed. The run that owns this search decides what
                // to do next.
                return null;
            }

            $companyId = (int) $lockedSearch->company_id;

            $lockedCandidate = Candidate::query()
                ->whereKey($candidate->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedCandidate instanceof Candidate
                || (int) $lockedCandidate->materials_revision !== $expectedMaterialsRevision) {
                // The candidate's material changed — or the candidate went away —
                // while the provider was working: a CV became readable, was
                // archived, deleted, or had its declared date corrected. This
                // answer describes evidence the workspace no longer holds in that
                // form, so nothing is persisted. Writing it with the new revision
                // would present an in-flight analysis as if it had read the
                // current material, and writing it with the old one would store a
                // row that is outdated the instant it is created.
                return null;
            }

            if ((int) $candidate->company_id !== $companyId || (int) $job->company_id !== $companyId) {
                throw ValidationException::withMessages([
                    'scores' => 'A sourcing match can only be stored for a candidate and job in the search\'s own workspace.',
                ]);
            }

            /** @var array{scores: array<int, array<string, mixed>>} $validated */
            $validated = Validator::make(['scores' => $scores], $this->criterionScoreRules())->validate();

            $criteria = $this->authoritativeCriteria($job->jobCriteria, $companyId);

            $this->assertCriteriaMatchExactly($criteria, $validated['scores']);

            $rows = $this->criterionScoreRows(
                $validated['scores'],
                $criteria,
                $companyId,
                fn (array $item): array => $this->evidenceItem($item, $materials),
            );

            $match = $this->matchFor($lockedSearch, $candidate, $companyId);

            $match->forceFill([
                // The revision this assessment measured — the one the request was
                // built from, verified above to still be the confirmed one, never
                // "whatever revision the job happens to carry now".
                'criteria_generation' => $expectedCriteriaGeneration,
                // The material revision this assessment read, verified above to
                // still be the candidate's current one. Recorded for the same
                // reason as the criteria revision: it is what lets a later
                // material change mark exactly this candidate's match as
                // outdated, and nobody else's.
                'materials_revision' => $expectedMaterialsRevision,
                'potential_match' => $this->potentialMatch($rows),
                'evidence_coverage' => $this->evidenceCoverage($rows),
                'confidence' => $this->matchConfidence($rows),
                // Reaching this action at all means the candidate had material
                // worth sending; insufficiently known candidates are counted by
                // the run and never scored.
                'sufficient_information' => true,
                'analyzed_at' => now(),
            ])->save();

            // Replaced wholesale rather than merged: the previous run's criterion
            // rows describe material and criteria this one has just reassessed,
            // and a leftover row would read as part of the current result.
            $match->criterionScores()->delete();
            $match->criterionScores()->createMany($rows);

            return $match;
        });
    }

    /**
     * Sourcing evidence names *which* material it came from, not only what kind
     * of material it was.
     *
     * The candidate's material spans their whole history, where a source can
     * repeat — two answers to two jobs, two resumes years apart — so `source`
     * alone cannot place a citation in time. `material_index` can, and it is
     * required for the same reason `criterion_id` is: identity is an index the
     * caller controls, never text the model echoed back.
     *
     * @return array<string, list<string>>
     */
    protected function evidenceItemRules(): array
    {
        return [
            'scores.*.evidence.*' => ['required', 'array:material_index,source,detail'],
            'scores.*.evidence.*.material_index' => ['required', 'integer', 'min:0'],
            'scores.*.evidence.*.source' => ['required', 'string', 'in:'.implode(',', CriterionEvidenceSource::values())],
            'scores.*.evidence.*.detail' => ['required', 'string', 'max:180'],
        ];
    }

    /**
     * One evidence item as it is stored, with the submission date resolved in PHP
     * from the index rather than taken from the response.
     *
     * The date is dropped, and only the date, when the index points at nothing or
     * at material of a different source than the one cited. Both mean the
     * citation cannot be placed, and an unplaceable date shown next to real
     * evidence would be a fact the product does not have. This is deliberately
     * softer than the criterion mapping, which fails the whole response on an
     * unknown ID: a criterion decides what was assessed and with what weight, so
     * getting it wrong corrupts the score itself, while the date only tells the
     * recruiter how old the support is. Losing it costs context; inventing it
     * would cost trust; discarding an otherwise sound assessment over it would
     * cost the recruiter the answer they asked for.
     *
     * `material_index` itself is not persisted: it addresses a list that only
     * existed for the duration of the request, and would be meaningless — or
     * quietly wrong — the next time the candidate's material is gathered.
     *
     * @param  array<string, mixed>  $item
     * @param  list<CandidateSourcingMaterial>  $materials
     * @return array{source: string, detail: string, submitted_at: string|null}
     */
    private function evidenceItem(array $item, array $materials): array
    {
        $material = $materials[(int) $item['material_index']] ?? null;
        $source = (string) $item['source'];

        return [
            'source' => $source,
            'detail' => (string) $item['detail'],
            'submitted_at' => $material instanceof CandidateSourcingMaterial && $material->source->value === $source
                ? $material->submittedAt?->toDateString()
                : null,
        ];
    }

    /**
     * The existing pairing, or a brand-new suggestion.
     *
     * The row is locked rather than replaced: `state`, `state_changed_at` and
     * `state_changed_by_id` are the recruiter's record of a decision they made
     * about this person for this job, and re-running an AI analysis is not a
     * reason to lose it. Only a pairing that has never been surfaced starts as
     * suggested, which is also why the unique (job, candidate) key exists — a
     * refresh updates, it does not accumulate second opinions.
     */
    private function matchFor(SourcingSearch $search, Candidate $candidate, int $companyId): SourcingMatch
    {
        $match = SourcingMatch::query()
            ->where('job_id', $search->job_id)
            ->where('candidate_id', $candidate->getKey())
            ->lockForUpdate()
            ->first();

        if ($match instanceof SourcingMatch) {
            return $match;
        }

        $match = new SourcingMatch;

        $match->forceFill([
            'company_id' => $companyId,
            'job_id' => $search->job_id,
            'candidate_id' => $candidate->getKey(),
            'state' => SourcingMatchState::Suggested,
        ]);

        return $match;
    }

    /**
     * Potential match, stored as a whole number because that is the precision
     * the recruiter is shown; the weighted-average-over-assessed-criteria rule
     * itself is the shared one, so sourcing and application fit can never start
     * meaning different things.
     *
     * Null when nothing could be assessed — the candidate's material did not
     * support a judgement, which is not a match of zero.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function potentialMatch(array $rows): ?int
    {
        $fit = $this->overallFit($rows);

        return $fit === null ? null : (int) round($fit);
    }

    /**
     * The match's confidence is the weakest confidence among the criteria that
     * could actually be assessed.
     *
     * Deliberately conservative: this figure sits next to a potential match in a
     * list the recruiter skims, and averaging it would let one well-evidenced
     * criterion speak for several vague ones. Per-criterion confidence stays
     * available underneath, so nothing is hidden by summarising cautiously.
     *
     * Low when nothing was assessable at all, for the same reason a null score
     * is normalised to low confidence: no support is not strong support.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function matchConfidence(array $rows): AnalysisConfidence
    {
        $assessed = array_values(array_filter($rows, fn (array $row): bool => $row['score'] !== null));

        if ($assessed === []) {
            return AnalysisConfidence::Low;
        }

        foreach ([AnalysisConfidence::Low, AnalysisConfidence::Medium] as $confidence) {
            foreach ($assessed as $row) {
                if ($row['confidence'] === $confidence->value) {
                    return $confidence;
                }
            }
        }

        return AnalysisConfidence::High;
    }
}
