<?php

namespace App\Actions;

use App\Enums\JobCriteriaProcessingStatus;
use App\Models\Application;
use App\Models\Job;
use Illuminate\Support\Facades\DB;

/**
 * An evaluation-relevant job edit produced a new criteria revision.
 *
 * Advancing `criteria_generation` does two jobs at once, which is why the
 * counter is reused rather than duplicated: an extraction still in flight can no
 * longer overwrite the criteria being edited, and evaluations produced against
 * the previous revision stop presenting themselves as current — see
 * {@see Application::hasCurrentEvaluation()} — without touching a single stored
 * score.
 *
 * Criteria that already govern the job stay governing it: the recruiter's edit
 * is authoritative the moment it is saved, so the confirmation columns are
 * re-pointed at the new revision instead of parking the job in a state that
 * waits for a second approval click. The applications still in process are then
 * released for evaluation against the revision that now applies.
 */
class RequireJobCriteriaReview
{
    public function __construct(
        private readonly ReleaseApplicationsForCurrentCriteria $releaseApplicationsForCurrentCriteria,
    ) {}

    public function handle(Job $job): void
    {
        $revised = DB::transaction(function () use ($job): bool {
            $lockedJob = Job::query()->whereKey($job->getKey())->lockForUpdate()->firstOrFail();

            // A blank job has no criteria revision to invalidate. In particular,
            // an early edit before its description becomes substantive must not
            // make the later initial extraction look like a regeneration.
            if ($lockedJob->criteria_processing_status === JobCriteriaProcessingStatus::NotStarted
                && $lockedJob->criteria_confirmed_generation === null
                && $lockedJob->criteria_confirmed_at === null
                && $lockedJob->criteria_confirmed_by_id === null
                && ! $lockedJob->jobCriteria()->exists()) {
                $job->setRawAttributes($lockedJob->getAttributes(), true);

                return false;
            }

            $generation = $lockedJob->criteria_generation + 1;

            // A running extraction describes an older definition after a
            // relevant edit. Invalidate its generation and make recovery
            // explicit rather than showing the old run as still current.
            $interrupted = in_array($lockedJob->criteria_processing_status, [
                JobCriteriaProcessingStatus::Pending,
                JobCriteriaProcessingStatus::Processing,
            ], strict: true);

            $carriesCriteria = ! $interrupted && $lockedJob->criteria_processing_status->hasCriteria();

            $lockedJob->forceFill([
                'criteria_processing_status' => $interrupted
                    ? JobCriteriaProcessingStatus::Failed
                    : ($carriesCriteria
                        ? JobCriteriaProcessingStatus::Completed
                        : $lockedJob->criteria_processing_status),
                'criteria_generation' => $generation,
                ...$carriesCriteria ? [
                    'criteria_confirmed_generation' => $generation,
                    'criteria_confirmed_at' => now(),
                    'criteria_confirmed_by_id' => null,
                ] : [],
            ])->saveQuietly();

            $job->setRawAttributes($lockedJob->getAttributes(), true);

            return $carriesCriteria;
        });

        if ($revised) {
            $this->releaseApplicationsForCurrentCriteria->handle($job, trigger: 'criteria_revised');
        }
    }
}
