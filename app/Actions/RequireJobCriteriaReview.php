<?php

namespace App\Actions;

use App\Enums\JobCriteriaProcessingStatus;
use App\Models\Application;
use App\Models\Job;
use Illuminate\Support\Facades\DB;

/**
 * The stored evaluation criteria no longer match what a recruiter confirmed, so
 * they need confirming again.
 *
 * Advancing `criteria_generation` does two jobs at once, which is why the
 * counter is reused rather than duplicated: an extraction still in flight can no
 * longer overwrite the criteria being edited, and the recorded confirmation
 * stops matching the current revision. Evaluations produced against the previous
 * revision therefore stop presenting themselves as current — see
 * {@see Application::hasCurrentEvaluation()} — without touching a
 * single stored score.
 *
 * Nothing is re-evaluated here. {@see ConfirmJobCriteria} does that, once a
 * human has looked at the criteria again.
 */
class RequireJobCriteriaReview
{
    public function handle(Job $job): void
    {
        DB::transaction(function () use ($job): void {
            $lockedJob = Job::query()->whereKey($job->getKey())->lockForUpdate()->firstOrFail();

            $lockedJob->forceFill([
                // A running extraction describes an older definition after a
                // relevant edit. Invalidate its generation and make recovery
                // explicit rather than showing the old run as still current.
                'criteria_processing_status' => match ($lockedJob->criteria_processing_status) {
                    JobCriteriaProcessingStatus::Pending,
                    JobCriteriaProcessingStatus::Processing => JobCriteriaProcessingStatus::Failed,
                    default => $lockedJob->criteria_processing_status->hasCriteria()
                        ? JobCriteriaProcessingStatus::AwaitingReview
                        : $lockedJob->criteria_processing_status,
                },
                'criteria_generation' => $lockedJob->criteria_generation + 1,
            ])->saveQuietly();

            $job->setRawAttributes($lockedJob->getAttributes(), true);
        });
    }
}
