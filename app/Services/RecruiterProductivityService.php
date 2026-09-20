<?php

namespace App\Services;

use App\Data\RecruiterProductivitySnapshot;
use App\Enums\AiExecutionOrigin;
use App\Enums\AiUsageStatus;
use App\Enums\ApplicationAnalysisStatus;
use App\Enums\JobCriteriaProcessingStatus;
use App\Enums\Limit;
use App\Enums\SourcingSearchStatus;
use App\Jobs\AnalyzeApplicationFit;
use App\Jobs\AnalyzeJobCriteria;
use App\Models\AiUsageRecord;
use App\Models\Application;
use App\Models\Company;
use App\Models\Job;
use App\Models\SourcingSearch;
use Carbon\CarbonImmutable;

/**
 * How much recruiter work Recruiter Labs actually completed for one workspace
 * in the recent period — the counterpart of {@see AiActivityService}, which
 * answers what it is doing right now.
 *
 * Three rules make these numbers honest, and none of them may be relaxed to
 * make the surface look busier:
 *
 * 1. **Only work that is still valid counts.** An evaluation produced against
 *    a criteria revision the job has since replaced did not save anyone any
 *    review time — it has to be redone — so it is excluded even though the
 *    provider call really happened. Same for criteria that are no longer the
 *    job's current set.
 * 2. **Only automatic work counts.** A recruiter who clicked "reprocess" did
 *    the deciding themselves; that run is real AI usage (and is billed as
 *    such in AI Settings) but it is not work the product did instead of them.
 * 3. **Only work completed inside the period counts**, read from the moment
 *    the result landed (`analyzed_at`, `criteria_confirmed_at`,
 *    `completed_at`), never from record creation.
 *
 * Origin is not persisted on the application or the job, so it is read from
 * the latest completed {@see AiUsageRecord} for that record and operation —
 * the run that produced the result being counted.
 */
class RecruiterProductivityService
{
    /**
     * The period every figure on this surface describes. One period, stated in
     * the copy: mixing "today" and "this month" in one region would make the
     * numbers unreadable and invite the most flattering framing.
     */
    public function periodStart(): CarbonImmutable
    {
        return CarbonImmutable::instance(now())->startOfWeek();
    }

    public function for(Company $company): RecruiterProductivitySnapshot
    {
        $start = $this->periodStart();
        $end = CarbonImmutable::instance(now());

        return new RecruiterProductivitySnapshot(
            periodStart: $start,
            periodEnd: $end,
            applicationsEvaluated: $this->applicationsEvaluatedAutomatically($company, $start, $end),
            criteriaPrepared: $this->criteriaPreparedAutomatically($company, $start, $end),
            sourcingProfilesAnalyzed: $this->sourcingProfilesAnalyzed($company, $start, $end),
            manualReviewMinutes: $company->manual_review_minutes_per_application,
            aiAllowance: app(CompanyUsageService::class)->usageFor($company, Limit::AiAnalyses),
        );
    }

    /**
     * Applications whose evaluation finished in the period, still measures the
     * job's current criteria, and was started by the product rather than by a
     * recruiter.
     */
    private function applicationsEvaluatedAutomatically(Company $company, CarbonImmutable $start, CarbonImmutable $end): int
    {
        $applicationTable = (new Application)->getTable();
        $jobTable = (new Job)->getTable();

        /** @var list<int> $applicationIds */
        $applicationIds = Application::query()
            ->withoutGlobalScopes()
            ->where("{$applicationTable}.company_id", $company->getKey())
            ->where("{$applicationTable}.analysis_status", ApplicationAnalysisStatus::Completed)
            ->whereNotNull("{$applicationTable}.analyzed_at")
            ->whereBetween("{$applicationTable}.analyzed_at", [$start, $end])
            ->whereNotNull("{$applicationTable}.analysis_criteria_generation")
            // The persisted form of Application::hasCurrentEvaluation(): the
            // evaluation measured the revision the job still governs itself by.
            ->whereExists(fn ($query) => $query
                ->from($jobTable)
                ->whereColumn("{$jobTable}.id", "{$applicationTable}.job_id")
                ->where("{$jobTable}.criteria_processing_status", JobCriteriaProcessingStatus::Completed)
                ->whereColumn("{$jobTable}.criteria_confirmed_generation", "{$jobTable}.criteria_generation")
                ->whereColumn("{$jobTable}.criteria_generation", "{$applicationTable}.analysis_criteria_generation"))
            ->pluck("{$applicationTable}.id")
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($applicationIds === []) {
            return 0;
        }

        return count($this->automaticOf(
            $company,
            AnalyzeApplicationFit::OPERATION,
            'application_id',
            $applicationIds,
        ));
    }

    /**
     * Criteria sets that became the job's current ones inside the period,
     * prepared automatically. This is the second half of the automatic work
     * the recruiter no longer does by hand: writing out what the role actually
     * requires before anyone can be measured against it.
     */
    private function criteriaPreparedAutomatically(Company $company, CarbonImmutable $start, CarbonImmutable $end): int
    {
        $jobTable = (new Job)->getTable();

        /** @var list<int> $jobIds */
        $jobIds = Job::query()
            ->where('company_id', $company->getKey())
            ->where('criteria_processing_status', JobCriteriaProcessingStatus::Completed)
            ->whereNotNull('criteria_confirmed_at')
            ->whereBetween('criteria_confirmed_at', [$start, $end])
            ->whereColumn("{$jobTable}.criteria_confirmed_generation", "{$jobTable}.criteria_generation")
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($jobIds === []) {
            return 0;
        }

        return count($this->automaticOf(
            $company,
            AnalyzeJobCriteria::OPERATION,
            'job_id',
            $jobIds,
        ));
    }

    /**
     * Profiles an explicitly requested sourcing sweep actually read. This is
     * completed work ("42 profiles reviewed"), never a ranking: the product
     * does not present the pool in fit order on this surface, and sourcing a
     * recruiter never asked for is not counted at all.
     */
    private function sourcingProfilesAnalyzed(Company $company, CarbonImmutable $start, CarbonImmutable $end): int
    {
        return (int) SourcingSearch::query()
            ->where('company_id', $company->getKey())
            ->where('status', SourcingSearchStatus::Completed)
            ->whereNotNull('requested_by_id')
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$start, $end])
            ->sum('candidates_considered');
    }

    /**
     * Of the given records, the ones whose most recent completed run of this
     * operation was automatic. A later user-requested retry therefore removes
     * the record from the count: the result the recruiter is looking at is the
     * one they asked for.
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function automaticOf(Company $company, string $operation, string $column, array $ids): array
    {
        $records = AiUsageRecord::query()
            ->where('company_id', $company->getKey())
            ->where('operation', $operation)
            ->where('status', AiUsageStatus::Completed)
            ->whereIn($column, $ids)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get([$column, 'origin', 'created_at', 'id']);

        /** @var array<int, AiExecutionOrigin> $latestOrigin */
        $latestOrigin = [];

        foreach ($records as $record) {
            $key = (int) $record->getAttribute($column);

            if (! array_key_exists($key, $latestOrigin)) {
                $latestOrigin[$key] = $record->origin;
            }
        }

        return array_values(array_filter(
            $ids,
            fn (int $id): bool => ($latestOrigin[$id] ?? null) === AiExecutionOrigin::Automatic,
        ));
    }
}
