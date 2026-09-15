<?php

namespace App\Actions;

use App\Enums\AiExecutionOrigin;
use App\Enums\SourcingSearchStatus;
use App\Models\Company;
use App\Models\Job;
use App\Models\SourcingSearch;
use App\Models\User;
use App\Services\CandidateSourcingEligibilityService;

/**
 * Revalidates the derived Attention sourcing gate immediately before starting
 * a search. Manual sourcing deliberately uses {@see RunSourcingSearch}
 * directly, because a recruiter may explicitly request a refresh there.
 */
class RunAttentionSourcingSearch
{
    public function __construct(
        private readonly CandidateSourcingEligibilityService $eligibility,
        private readonly RunSourcingSearch $runSourcingSearch,
    ) {}

    public function handle(Company $company, User $user, int $jobId, string $intent): bool
    {
        if (! in_array($intent, ['start_sourcing', 'refresh_sourcing'], true)) {
            return false;
        }

        $job = Job::query()
            ->whereBelongsTo($company)
            ->whereKey($jobId)
            ->first();

        if (! $job instanceof Job) {
            return false;
        }

        return $this->runSourcingSearch->handle(
            $job,
            (int) $user->getKey(),
            AiExecutionOrigin::UserRequested,
            'sourcing_requested_from_attention',
            function (Job $lockedJob, SourcingSearch $search) use ($company, $intent): bool {
                $lockedJob->setRelation('company', $company);
                $search->setRelation('job', $lockedJob);
                $search->setRelation('company', $company);

                if ($search->status->isInProgress()
                    || ! $lockedJob->hasConfirmedCriteria()
                    || ! $lockedJob->jobCriteria()->exists()
                    || $this->eligibility->eligibleCandidateCount($lockedJob) === 0) {
                    return false;
                }

                return match ($intent) {
                    'start_sourcing' => $search->status === SourcingSearchStatus::NotStarted,
                    'refresh_sourcing' => $search->isOutdated(),
                };
            },
        );
    }
}
