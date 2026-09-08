<?php

namespace App\Actions;

use App\Exceptions\PlanLimitExceededException;
use App\Exceptions\RecruitmentWorkflowException;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Job;
use App\Services\ApplicationAvailabilityService;
use App\Services\LimitManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

/**
 * The single path through which a recruiter puts an existing candidate onto a
 * job without the candidate applying. Every caller — the pipeline's manual "add
 * candidate" action, sourcing's "add to job" — goes through here, so the plan
 * limit, the duplicate guard and the initial status can never be bypassed by a
 * plain `Application::create()`.
 *
 * The application it creates is an ordinary one: the source stays at its default
 * so a sourced candidate is indistinguishable from any other once in the
 * pipeline.
 *
 * Business rules only: the failures below are thrown as domain exceptions and
 * the caller decides how to surface them.
 */
class AddCandidateToJob
{
    public function __construct(
        private readonly LimitManager $limitManager,
        private readonly ApplicationAvailabilityService $applicationAvailabilityService,
    ) {}

    /**
     * @throws PlanLimitExceededException when the workspace is over its application allowance
     * @throws RecruitmentWorkflowException when the candidate is not eligible for this job
     * @throws ValidationException when the job's pipeline has no status to enter
     */
    public function handle(Job $job, Candidate $candidate): Application
    {
        $company = $job->company;

        abort_unless($company instanceof Company, 404);

        $this->limitManager->ensureCanReceiveApplication($company);

        // Re-checked here rather than trusted from the caller's selection list:
        // the candidate may have applied — or been added by someone else —
        // between the list being built and this call.
        $isEligible = Candidate::query()
            ->where('company_id', $job->company_id)
            ->whereKey($candidate->getKey())
            ->whereDoesntHave(
                'applications',
                fn (Builder $query): Builder => $query->where('job_id', $job->id),
            )
            ->exists();

        if (! $isEligible) {
            throw RecruitmentWorkflowException::candidateAlreadyInJob();
        }

        $initialStatus = $this->applicationAvailabilityService->initialStatus($job);

        try {
            return Application::query()->create([
                'company_id' => $job->company_id,
                'job_id' => $job->id,
                'candidate_id' => $candidate->getKey(),
                'status_id' => $initialStatus->getKey(),
            ]);
        } catch (QueryException) {
            // The database-level unique constraint is the last line of defence
            // against two recruiters adding the same candidate at once.
            throw RecruitmentWorkflowException::candidateAlreadyInJob();
        }
    }
}
