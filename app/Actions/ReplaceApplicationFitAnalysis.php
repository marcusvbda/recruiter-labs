<?php

namespace App\Actions;

use App\Actions\Concerns\PersistsCriterionScoreResults;
use App\Enums\ApplicationAnalysisStatus;
use App\Enums\CompanyMilestone;
use App\Models\Application;
use App\Models\ApplicationCriterionScore;
use App\Models\ApplicationInterviewBriefItem;
use App\Models\Job;
use App\Models\JobCriterion;
use App\Services\AiActivityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Marcusvbda\FilamentRealtimeDriver\RealtimeEvent;

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
 * - **Criterion IDs prove mapping identity; the criteria generation proves
 *   semantic revision identity, and both are required.** A recruiter can rewrite
 *   a criterion's text, meaning and weight while its database row keeps the same
 *   ID, so "every returned ID still exists" is no evidence that the response
 *   describes the criteria this hiring process runs on now. The evaluation is
 *   therefore requested against, and persisted against, one exact confirmed
 *   criteria revision.
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
    use PersistsCriterionScoreResults;

    /**
     * @param  array<int, mixed>  $scores
     * @param  array<int, mixed>  $interviewBriefItems
     * @param  int  $expectedGeneration  The application analysis generation this
     *                                   response was requested for.
     * @param  int  $expectedCriteriaGeneration  The confirmed job criteria
     *                                           revision the request was built
     *                                           from.
     * @return bool Whether the evaluation was persisted. False when the response
     *              no longer describes the current state: a newer analysis is in
     *              flight, or the criteria revision it measured is no longer the
     *              confirmed one.
     */
    public function handle(
        Application $application,
        array $scores,
        array $interviewBriefItems,
        int $expectedGeneration,
        int $expectedCriteriaGeneration,
    ): bool {
        return DB::transaction(function () use ($application, $scores, $interviewBriefItems, $expectedGeneration, $expectedCriteriaGeneration): bool {
            $lockedApplication = Application::query()->whereKey($application->getKey())->lockForUpdate()->first();

            if ($lockedApplication === null || $lockedApplication->analysis_generation !== $expectedGeneration) {
                return false;
            }

            $job = Job::query()
                ->whereKey($lockedApplication->job_id)
                ->lockForUpdate()
                ->firstOrFail();
            $job->load('jobCriteria');

            if (! $this->criteriaRevisionIsStill($job, $expectedCriteriaGeneration)) {
                // The recruiter changed the criteria while the provider request
                // was running, so this answer describes a revision that no longer
                // governs the process. Nothing is persisted — not the scores, not
                // the evidence, not the brief, not the fit, and not the revision
                // link — and the application goes back to waiting for criteria so
                // confirming the new revision reschedules it through the normal
                // path. This is an expected concurrency outcome, not a provider
                // failure, so it does not throw: retrying would only replay the
                // same stale response.
                //
                // For the same reason no evaluation milestone is captured here.
                // The provider answered, but the recruiter has no evaluation to
                // read, so counting this as the workspace's first evaluated
                // candidate would present activation progress the product cannot
                // show.
                $lockedApplication->forceFill([
                    'analysis_status' => ApplicationAnalysisStatus::AwaitingCriteria,
                ])->saveQuietly();

                $this->broadcastAnalysisUpdated($lockedApplication);

                $application->setRawAttributes($lockedApplication->getAttributes(), true);

                return false;
            }

            $validated = $this->validateResponse($scores, $interviewBriefItems);

            $criteria = $this->authoritativeCriteria($job->jobCriteria, (int) $lockedApplication->company_id);

            $this->assertCriteriaMatchExactly($criteria, $validated['scores']);

            // Position is the link between the validated response and the rows
            // that get written, so a job with two identically worded criteria
            // still maps each result to its own criterion.
            $criterionIdOrder = array_map(fn (array $result): int => (int) $result['criterion_id'], $validated['scores']);

            $rows = $this->criterionScoreRows($validated['scores'], $criteria, (int) $lockedApplication->company_id);

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
                // The revision this evaluation measured — the one the request
                // was built from, verified above to still be the confirmed one,
                // never "whatever revision the job happens to carry now".
                // Without it, a criteria change would leave this fit quietly
                // presenting itself as the current assessment.
                'analysis_criteria_generation' => $expectedCriteriaGeneration,
                'analysis_score' => $this->overallFit($rows),
                'analysis_coverage' => $this->evidenceCoverage($rows),
                'analyzed_at' => now(),
            ])->saveQuietly();

            $this->broadcastAnalysisUpdated($lockedApplication);

            // Captured only here, where a completed evaluation is persisted: this
            // is the single point in the action where the recruiter actually gains
            // a fit, coverage and confidence assessment to read. Failed,
            // quota-held, cancelled and stale-revision executions never reach it.
            app(CaptureCompanyMilestone::class)->handle((int) $lockedApplication->company_id, CompanyMilestone::FirstApplicationEvaluated);

            return true;
        });
    }

    /**
     * Drives the application's AI analysis panel realtime refresh
     * (ai-analysis-pending.blade.php / ai-analysis-processing.blade.php)
     * instead of polling. Both writes above use `saveQuietly()` on purpose
     * (see their own comments), so they bypass model events and must
     * broadcast explicitly.
     */
    private function broadcastAnalysisUpdated(Application $application): void
    {
        RealtimeEvent::dispatch('application_analysis_'.$application->getKey(), 'ApplicationAnalysisUpdated');

        // The same write also changes what the workspace-wide AI Activity
        // indicator should say.
        AiActivityService::broadcast($application->company);
    }

    /**
     * @param  array<int, mixed>  $scores
     * @param  array<int, mixed>  $interviewBriefItems
     * @return array{scores: array<int, array<string, mixed>>, interview_brief_items: array<int, array<string, mixed>>}
     */
    private function validateResponse(array $scores, array $interviewBriefItems): array
    {
        /** @var array{scores: array<int, array<string, mixed>>, interview_brief_items: array<int, array<string, mixed>>} $validated */
        $validated = Validator::make(
            ['scores' => $scores, 'interview_brief_items' => $interviewBriefItems],
            [
                ...$this->criterionScoreRules(),
                'interview_brief_items' => ['present', 'array', 'max:6'],
                'interview_brief_items.*' => ['required', 'array:criterion_id,priority,reason,question'],
                'interview_brief_items.*.criterion_id' => ['required', 'integer'],
                'interview_brief_items.*.priority' => ['required', 'string', 'in:high,medium,low'],
                'interview_brief_items.*.reason' => ['required', 'string', 'max:220'],
                'interview_brief_items.*.question' => ['required', 'string', 'max:300'],
            ],
        )->validate();

        return $validated;
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
}
