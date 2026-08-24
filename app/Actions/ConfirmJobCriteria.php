<?php

namespace App\Actions;

use App\Enums\ApplicationAnalysisStatus;
use App\Enums\JobCriteriaProcessingStatus;
use App\Models\Application;
use App\Models\Job;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
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
 *
 * The human is not optional. A confirmed revision with no confirmer recorded
 * would be an AI suggestion that promoted itself, so the confirming user is a
 * required argument and has to belong to the job's workspace. This is not a
 * roles model — no owner, recruiter or hiring-manager distinction is implied —
 * only "the confirmer is an authenticated human belonging to this workspace".
 * Seeders and factories that need confirmed criteria without a recruiter action
 * write the columns themselves; the domain action does not relax for them.
 */
class ConfirmJobCriteria
{
    public function __construct(private readonly ScheduleApplicationFitAnalysis $scheduleApplicationFitAnalysis) {}

    /**
     * @param  User  $user  The human confirming these criteria. Recorded as
     *                      `criteria_confirmed_by_id`, and required to be a
     *                      member of the job's company.
     * @return bool Whether the confirmation was recorded. False when there is
     *              nothing to confirm: no criteria stored, or an extraction is
     *              still in flight and would overwrite what is being confirmed.
     *
     * @throws AuthorizationException When the user does not belong to the job's
     *                                workspace.
     */
    public function handle(Job $job, User $user): bool
    {
        $confirmed = DB::transaction(function () use ($job, $user): bool {
            $lockedJob = Job::query()->whereKey($job->getKey())->lockForUpdate()->firstOrFail();

            $this->assertBelongsToWorkspace($lockedJob, $user);

            if (! $lockedJob->criteria_processing_status->hasCriteria() || ! $lockedJob->jobCriteria()->exists()) {
                return false;
            }

            $lockedJob->forceFill([
                'criteria_processing_status' => JobCriteriaProcessingStatus::Completed,
                'criteria_confirmed_generation' => $lockedJob->criteria_generation,
                'criteria_confirmed_at' => now(),
                'criteria_confirmed_by_id' => $user->getKey(),
            ])->saveQuietly();

            $job->setRawAttributes($lockedJob->getAttributes(), true);

            return true;
        });

        if ($confirmed) {
            $this->scheduleEligibleApplications($job, (int) $user->getKey());
        }

        return $confirmed;
    }

    /**
     * Membership in the job's company, read from the current tenancy model. It
     * deliberately asks nothing about what the user may do beyond that.
     *
     * @throws AuthorizationException
     */
    private function assertBelongsToWorkspace(Job $job, User $user): void
    {
        if (! $user->exists || $user->getKey() === null || ! User::query()
            ->whereKey($user->getKey())
            ->whereHas('companies', fn ($companies) => $companies->whereKey($job->company_id))
            ->exists()) {
            throw new AuthorizationException('Only a member of this workspace may confirm the job\'s evaluation criteria.');
        }
    }

    /**
     * Candidates whose evaluation is either still waiting for criteria or was
     * produced against a superseded revision. Applications whose process already
     * ended keep their historical evaluation: re-running an evaluation for
     * somebody who was rejected months ago spends AI allowance on a decision
     * nobody is going to make again.
     */
    private function scheduleEligibleApplications(Job $job, int $userId): void
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
