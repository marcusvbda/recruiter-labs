<?php

namespace App\Services;

use App\Exceptions\PlanLimitExceededException;
use App\Models\Company;
use App\Models\Job;
use App\Models\Status;
use Illuminate\Validation\ValidationException;

class ApplicationAvailabilityService
{
    public function __construct(private readonly LimitManager $limitManager) {}

    public function lockAndEnsureCanReceive(Job $job): Job
    {
        $company = Company::query()
            ->with('plan')
            ->lockForUpdate()
            ->find($job->company_id);

        if (! $company instanceof Company) {
            throw ValidationException::withMessages([
                '_form' => __('job_application.errors.job_unavailable'),
            ]);
        }

        $lockedJob = Job::query()
            ->whereBelongsTo($company)
            ->lockForUpdate()
            ->find($job->getKey());

        if (! $lockedJob instanceof Job || ! $lockedJob->acceptsApplications()) {
            throw ValidationException::withMessages([
                '_form' => __('job_application.errors.job_unavailable'),
            ]);
        }

        try {
            $this->limitManager->ensureCanReceiveApplication($company);
        } catch (PlanLimitExceededException) {
            throw ValidationException::withMessages([
                '_form' => __('job_application.errors.limit_reached'),
            ]);
        }

        if (
            $lockedJob->application_limit !== null
            && $lockedJob->applications()->count() >= $lockedJob->application_limit
        ) {
            throw ValidationException::withMessages([
                '_form' => __('job_application.errors.limit_reached'),
            ]);
        }

        return $lockedJob->setRelation('company', $company);
    }

    /**
     * The status a new application enters: the first one, in configured order, of
     * the pipeline the job was created with. There is deliberately no fallback to
     * some other status of the company — a job whose pipeline has no statuses is a
     * misconfiguration, and applications fail safely rather than landing in an
     * arbitrary workflow.
     */
    public function initialStatus(Job $job): Status
    {
        $status = Status::query()
            ->where('company_id', $job->company_id)
            ->where('pipeline_id', $job->pipeline_id)
            ->orderBy('order')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if (! $status instanceof Status) {
            throw ValidationException::withMessages([
                '_form' => __('job_application.errors.job_unavailable'),
            ]);
        }

        return $status;
    }
}
