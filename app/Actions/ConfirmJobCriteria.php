<?php

namespace App\Actions;

use App\Enums\ApplicationAnalysisStatus;
use App\Enums\JobCriteriaProcessingStatus;
use App\Models\Application;
use App\Models\Job;
use Illuminate\Support\Facades\DB;

/**
 * A recruiter confirms the criteria that will govern candidate evaluation.
 *
 * This is the gate the whole evaluation thesis rests on: AI proposes, a human
 * decides. Extraction finishing leaves the criteria in
 * {@see JobCriteriaProcessingStatus::AwaitingReview}; only this action makes a
 * revision authoritative, and it records who confirmed it and when.
 *
 * Confirming releases the candidate evaluations that were waiting for it, and
 * refreshes evaluations that measured an older revision — through the existing
 * {@see ScheduleApplicationFitAnalysis} path, so quota, queue and locking
 * behaviour are unchanged. It performs no recruitment decision of its own.
 */
class ConfirmJobCriteria
{
    public function __construct(private readonly ScheduleApplicationFitAnalysis $scheduleApplicationFitAnalysis) {}

    /**
     * @return bool Whether the confirmation was recorded. False when there is
     *              nothing to confirm: no criteria stored, or an extraction is
     *              still in flight and would overwrite what is being confirmed.
     */
    public function handle(Job $job, ?int $userId = null): bool
    {
        $confirmed = DB::transaction(function () use ($job, $userId): bool {
            $lockedJob = Job::query()->whereKey($job->getKey())->lockForUpdate()->firstOrFail();

            if (! $lockedJob->criteria_processing_status->hasCriteria() || ! $lockedJob->jobCriteria()->exists()) {
                return false;
            }

            $lockedJob->forceFill([
                'criteria_processing_status' => JobCriteriaProcessingStatus::Completed,
                'criteria_confirmed_generation' => $lockedJob->criteria_generation,
                'criteria_confirmed_at' => now(),
                'criteria_confirmed_by_id' => $userId,
            ])->saveQuietly();

            $job->setRawAttributes($lockedJob->getAttributes(), true);

            return true;
        });

        if ($confirmed) {
            $this->scheduleEligibleApplications($job, $userId);
        }

        return $confirmed;
    }

    /**
     * Candidates whose evaluation is either still waiting for criteria or was
     * produced against a superseded revision. Applications whose process already
     * ended keep their historical evaluation: re-running an evaluation for
     * somebody who was rejected months ago spends AI allowance on a decision
     * nobody is going to make again.
     */
    private function scheduleEligibleApplications(Job $job, ?int $userId): void
    {
        $job->applications()
            ->inProcess()
            ->where(fn ($query) => $query
                ->where('analysis_status', ApplicationAnalysisStatus::AwaitingCriteria)
                ->orWhere(fn ($stale) => $stale
                    ->where('analysis_status', ApplicationAnalysisStatus::Completed)
                    ->where(fn ($revision) => $revision
                        ->whereNull('analysis_criteria_generation')
                        ->orWhere('analysis_criteria_generation', '!=', $job->criteria_generation))))
            ->get()
            ->each(function (Application $application) use ($userId): void {
                $this->scheduleApplicationFitAnalysis->handle($application, $userId);
            });
    }
}
