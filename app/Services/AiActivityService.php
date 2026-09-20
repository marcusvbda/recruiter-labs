<?php

namespace App\Services;

use App\Data\AiActivityItem;
use App\Data\AiActivitySnapshot;
use App\Enums\AiActivityState;
use App\Enums\ApplicationAnalysisStatus;
use App\Enums\CandidateImportStatus;
use App\Enums\JobCriteriaProcessingStatus;
use App\Enums\SourcingSearchStatus;
use App\Filament\Resources\Applications\ApplicationResource;
use App\Filament\Resources\CandidateImportBatches\CandidateImportBatchResource;
use App\Filament\Resources\Jobs\JobResource;
use App\Models\Application;
use App\Models\CandidateImportBatch;
use App\Models\Company;
use App\Models\Job;
use App\Models\SourcingSearch;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Marcusvbda\FilamentRealtimeDriver\RealtimeEvent;

/**
 * What the AI is actually doing for one workspace, right now.
 *
 * Every line this service produces is read from a persisted operation state
 * column — {@see ApplicationAnalysisStatus}, {@see JobCriteriaProcessingStatus},
 * {@see SourcingSearchStatus} and {@see CandidateImportStatus}. There is no
 * queue introspection, no heartbeat and no synthetic progress: if no row is in
 * a running state, the workspace is up to date and the product says so, which
 * is a truthful and useful answer.
 *
 * Labels name the recruiting work being done ("Candidate reviewer —
 * Evaluating ..."), never a class, queue, worker or prompt name.
 */
class AiActivityService
{
    /**
     * Company-scoped realtime channel the indicator subscribes to. Any write
     * that moves one of the four status columns broadcasts
     * {@see self::EVENT} here, which is what replaces polling.
     */
    public const ChannelPrefix = 'ai_activity_';

    public const Event = 'AiActivityUpdated';

    /**
     * Above this many operations of the same kind in the same context, the
     * panel shows one aggregated line ("12 candidates being evaluated for
     * Backend Engineer") instead of one line per candidate. Four or more is
     * where a list stops being scannable in a compact panel.
     */
    public const AggregationThreshold = 3;

    /** How many recently completed items the panel keeps, per workspace. */
    public const RecentLimit = 15;

    /** How many individual (non-aggregated) lines a section may show. */
    private const MaxItemsPerSection = 12;

    /**
     * A failure older than this is workspace history, not a live block: it
     * stays visible through Attention and the record itself, but it stops
     * pinning the global indicator to "Blocked" forever.
     */
    private const BlockedWindowDays = 7;

    public function for(Company $company): AiActivitySnapshot
    {
        $working = [];
        $waiting = [];
        $blocked = [];

        $workingCount = 0;
        $waitingCount = 0;
        $blockedCount = 0;

        // --- Criteria preparation -------------------------------------------
        $criteriaJobs = $this->jobsByCriteriaStatus($company);

        foreach ($this->pick($criteriaJobs, JobCriteriaProcessingStatus::Pending, JobCriteriaProcessingStatus::Processing) as $job) {
            $working[] = $this->criteriaItem($job, 'preparing');
            $workingCount++;
        }

        foreach ($this->pick($criteriaJobs, JobCriteriaProcessingStatus::PendingQuota) as $job) {
            $waiting[] = $this->criteriaItem($job, 'waiting_allowance', (string) __('ai_activity.reason.ai_allowance'));
            $waitingCount++;
        }

        foreach ($this->pick($criteriaJobs, JobCriteriaProcessingStatus::Failed) as $job) {
            $blocked[] = $this->criteriaItem($job, 'failed', (string) __('ai_activity.reason.criteria_failed'));
            $blockedCount++;
        }

        // --- Candidate evaluation -------------------------------------------
        $applications = $this->applicationsByAnalysisStatus($company);

        [$items, $count] = $this->evaluationLines(
            $this->pick($applications, ApplicationAnalysisStatus::Pending, ApplicationAnalysisStatus::Processing),
            'evaluating',
        );
        $working = array_merge($working, $items);
        $workingCount += $count;

        [$items, $count] = $this->evaluationLines(
            $this->pick($applications, ApplicationAnalysisStatus::AwaitingCriteria),
            'waiting_criteria',
            (string) __('ai_activity.reason.job_criteria'),
        );
        $waiting = array_merge($waiting, $items);
        $waitingCount += $count;

        [$items, $count] = $this->evaluationLines(
            $this->pick($applications, ApplicationAnalysisStatus::PendingQuota),
            'waiting_allowance',
            (string) __('ai_activity.reason.ai_allowance'),
        );
        $waiting = array_merge($waiting, $items);
        $waitingCount += $count;

        [$items, $count] = $this->evaluationLines(
            $this->pick($applications, ApplicationAnalysisStatus::Failed),
            'failed',
            (string) __('ai_activity.reason.evaluation_failed'),
        );
        $blocked = array_merge($blocked, $items);
        $blockedCount += $count;

        // --- Talent pool review ---------------------------------------------
        $searches = $this->sourcingSearchesByStatus($company);

        foreach ($this->pick($searches, SourcingSearchStatus::Pending, SourcingSearchStatus::Processing) as $search) {
            $working[] = $this->sourcingItem($search, 'reviewing');
            $workingCount++;
        }

        foreach ($this->pick($searches, SourcingSearchStatus::PendingQuota) as $search) {
            $waiting[] = $this->sourcingItem($search, 'waiting_allowance', (string) __('ai_activity.reason.ai_allowance'));
            $waitingCount++;
        }

        foreach ($this->pick($searches, SourcingSearchStatus::Failed) as $search) {
            $blocked[] = $this->sourcingItem($search, 'failed', (string) __('ai_activity.reason.sourcing_failed'));
            $blockedCount++;
        }

        // --- Candidate import ------------------------------------------------
        $batches = $this->importBatchesByStatus($company);

        foreach ($this->pick($batches, CandidateImportStatus::Validating, CandidateImportStatus::Processing) as $batch) {
            $working[] = $this->importItem($batch, 'importing');
            $workingCount++;
        }

        foreach ($this->pick($batches, CandidateImportStatus::Paused) as $batch) {
            $waiting[] = $this->importItem($batch, 'paused', (string) __('ai_activity.reason.import_paused'));
            $waitingCount++;
        }

        foreach ($this->pick($batches, CandidateImportStatus::Failed) as $batch) {
            $blocked[] = $this->importItem($batch, 'failed', (string) __('ai_activity.reason.import_failed'));
            $blockedCount++;
        }

        return new AiActivitySnapshot(
            state: $this->state($workingCount, $waitingCount, $blockedCount),
            workingCount: $workingCount,
            waitingCount: $waitingCount,
            blockedCount: $blockedCount,
            working: array_slice($working, 0, self::MaxItemsPerSection),
            waiting: array_slice($waiting, 0, self::MaxItemsPerSection),
            blocked: array_slice($blocked, 0, self::MaxItemsPerSection),
            recent: $this->recentlyCompleted($company),
        );
    }

    /**
     * Tell every open indicator in this workspace that AI operation state
     * moved. Called from the lifecycle points that write the four status
     * columns — several of them use query-builder updates that bypass model
     * events, so the broadcast has to be explicit there.
     */
    public static function broadcast(Company|string|null $company): void
    {
        $slug = $company instanceof Company ? $company->slug : $company;

        if ($slug === null || $slug === '') {
            return;
        }

        RealtimeEvent::dispatch(self::ChannelPrefix.$slug, self::Event);
    }

    /** Broadcast for the workspace owning a job, without needing it loaded. */
    public static function broadcastForJob(int $jobId): void
    {
        self::broadcast(
            Job::query()->whereKey($jobId)->with('company')->first()?->company?->slug
        );
    }

    /** Broadcast for the workspace owning an application. */
    public static function broadcastForApplication(int $applicationId): void
    {
        self::broadcast(
            Application::query()->withoutGlobalScopes()->whereKey($applicationId)->with('company')->first()?->company?->slug
        );
    }

    /**
     * Blocked wins over working because a failure needs a person and running
     * work does not. Waiting only surfaces when nothing is actually running,
     * so a queue that is moving reads as movement.
     */
    private function state(int $working, int $waiting, int $blocked): AiActivityState
    {
        return match (true) {
            $blocked > 0 => AiActivityState::Blocked,
            $working > 0 => AiActivityState::Working,
            $waiting > 0 => AiActivityState::Waiting,
            default => AiActivityState::UpToDate,
        };
    }

    /**
     * One line per application below the aggregation threshold; one line per
     * job above it. The returned count is always the real number of
     * applications, whichever shape the lines took.
     *
     * @param  Collection<int, Application>  $applications
     * @return array{0: list<AiActivityItem>, 1: int}
     */
    private function evaluationLines(Collection $applications, string $key, ?string $reason = null): array
    {
        $items = [];
        $total = 0;

        foreach ($applications->groupBy('job_id') as $group) {
            $total += $group->count();
            $job = $group->first()?->job;

            if ($job === null) {
                continue;
            }

            if ($group->count() > self::AggregationThreshold) {
                $items[] = new AiActivityItem(
                    role: (string) __('ai_activity.role.candidate_reviewer'),
                    description: (string) trans_choice('ai_activity.work.evaluating_many', $group->count(), [
                        'count' => $group->count(),
                        'job' => $job->name,
                    ]),
                    url: $this->jobUrl($job),
                    reason: $reason,
                    count: $group->count(),
                );

                continue;
            }

            foreach ($group as $application) {
                $items[] = new AiActivityItem(
                    role: (string) __('ai_activity.role.candidate_reviewer'),
                    description: (string) __('ai_activity.work.evaluating_'.$key, [
                        'candidate' => $application->candidate->name,
                        'job' => $job->name,
                    ]),
                    url: $this->applicationUrl($application),
                    reason: $reason,
                );
            }
        }

        return [$items, $total];
    }

    private function criteriaItem(Job $job, string $key, ?string $reason = null): AiActivityItem
    {
        return new AiActivityItem(
            role: (string) __('ai_activity.role.criteria_analyst'),
            description: (string) __('ai_activity.work.criteria_'.$key, ['job' => $job->name]),
            url: $this->jobUrl($job),
            reason: $reason,
        );
    }

    private function sourcingItem(SourcingSearch $search, string $key, ?string $reason = null): AiActivityItem
    {
        $job = $search->job;

        return new AiActivityItem(
            role: (string) __('ai_activity.role.talent_matcher'),
            description: (string) __('ai_activity.work.sourcing_'.$key, [
                'job' => $job->name,
            ]),
            url: JobResource::getUrl('view', ['record' => $job, 'section' => 'sourcing'], tenant: $search->company),
            reason: $reason,
        );
    }

    private function importItem(CandidateImportBatch $batch, string $key, ?string $reason = null): AiActivityItem
    {
        $running = $key === 'importing';

        return new AiActivityItem(
            role: (string) __('ai_activity.role.candidate_importer'),
            description: (string) __('ai_activity.work.import_'.$key, ['source' => $batch->source_label]),
            url: $running
                ? CandidateImportBatchResource::getUrl('progress', ['record' => $batch], tenant: $batch->company)
                : CandidateImportBatchResource::getUrl('index', tenant: $batch->company),
            reason: $reason,
            occurredAt: $batch->completed_at,
        );
    }

    /**
     * A bounded, newest-first history of AI work that actually finished, so a
     * recruiter coming back to the tab can see what happened while they were
     * elsewhere. Failures are not in here: claiming failed work as completed
     * work is exactly what this surface must never do.
     *
     * @return list<AiActivityItem>
     */
    private function recentlyCompleted(Company $company): array
    {
        /** @var list<AiActivityItem> $items */
        $items = [];

        $evaluated = Application::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->getKey())
            ->where('analysis_status', ApplicationAnalysisStatus::Completed)
            ->whereNotNull('analyzed_at')
            ->with(['job', 'candidate'])
            ->orderByDesc('analyzed_at')
            ->limit(self::RecentLimit)
            ->get();

        foreach ($evaluated->groupBy('job_id') as $group) {
            $job = $group->first()?->job;

            if ($job === null) {
                continue;
            }

            if ($group->count() > self::AggregationThreshold) {
                $items[] = new AiActivityItem(
                    role: (string) __('ai_activity.role.candidate_reviewer'),
                    description: (string) trans_choice('ai_activity.done.evaluated_many', $group->count(), [
                        'count' => $group->count(),
                        'job' => $job->name,
                    ]),
                    url: $this->jobUrl($job),
                    count: $group->count(),
                    occurredAt: $group->max('analyzed_at'),
                );

                continue;
            }

            foreach ($group as $application) {
                $items[] = new AiActivityItem(
                    role: (string) __('ai_activity.role.candidate_reviewer'),
                    description: (string) __('ai_activity.done.evaluated', [
                        'candidate' => $application->candidate->name,
                        'job' => $job->name,
                    ]),
                    url: $this->applicationUrl($application),
                    occurredAt: $application->analyzed_at,
                );
            }
        }

        $criteriaReady = Job::query()
            ->where('company_id', $company->getKey())
            ->where('criteria_processing_status', JobCriteriaProcessingStatus::Completed)
            ->whereNotNull('criteria_confirmed_at')
            ->orderByDesc('criteria_confirmed_at')
            ->limit(self::RecentLimit)
            ->get();

        foreach ($criteriaReady as $job) {
            $items[] = new AiActivityItem(
                role: (string) __('ai_activity.role.criteria_analyst'),
                description: (string) __('ai_activity.done.criteria', ['job' => $job->name]),
                url: $this->jobUrl($job),
                occurredAt: $job->criteria_confirmed_at,
            );
        }

        $sourced = SourcingSearch::query()
            ->where('company_id', $company->getKey())
            ->where('status', SourcingSearchStatus::Completed)
            ->whereNotNull('completed_at')
            ->with('job')
            ->orderByDesc('completed_at')
            ->limit(self::RecentLimit)
            ->get();

        foreach ($sourced as $search) {
            $items[] = $this->sourcingItem($search, 'done')->withOccurredAt($search->completed_at);
        }

        $imported = CandidateImportBatch::query()
            ->where('company_id', $company->getKey())
            ->whereIn('status', [CandidateImportStatus::Completed, CandidateImportStatus::CompletedWithIssues])
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->limit(self::RecentLimit)
            ->get();

        foreach ($imported as $batch) {
            $items[] = $this->importItem($batch, 'done');
        }

        usort(
            $items,
            fn (AiActivityItem $a, AiActivityItem $b): int => ($b->occurredAt?->getTimestamp() ?? 0) <=> ($a->occurredAt?->getTimestamp() ?? 0),
        );

        return array_slice($items, 0, self::RecentLimit);
    }

    /**
     * The same destination {@see JobResource::getWorkspaceUrl()} means, but
     * with the tenant passed explicitly: this runs from queued work and from
     * a panel request alike, so it may not rely on a resolved current tenant.
     */
    private function jobUrl(Job $job): string
    {
        return JobResource::getUrl(
            'view',
            ['record' => $job, 'section' => 'pipeline'],
            tenant: $job->company,
        );
    }

    private function applicationUrl(Application $application): string
    {
        return ApplicationResource::getUrl('view', ['record' => $application], tenant: $application->company);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Collection<int, TModel>  $records
     * @return Collection<int, TModel>
     */
    private function pick(Collection $records, \BackedEnum ...$statuses): Collection
    {
        return new Collection(array_values(array_filter(
            $records->all(),
            fn ($record): bool => in_array($record->getAttribute($this->statusColumnFor($record)), $statuses, true),
        )));
    }

    private function statusColumnFor(object $record): string
    {
        return match (true) {
            $record instanceof Job => 'criteria_processing_status',
            $record instanceof Application => 'analysis_status',
            default => 'status',
        };
    }

    /** @return Collection<int, Job> */
    private function jobsByCriteriaStatus(Company $company): Collection
    {
        return Job::query()
            ->where('company_id', $company->getKey())
            ->whereIn('criteria_processing_status', [
                JobCriteriaProcessingStatus::Pending,
                JobCriteriaProcessingStatus::Processing,
                JobCriteriaProcessingStatus::PendingQuota,
                JobCriteriaProcessingStatus::Failed,
            ])
            ->where(fn ($query) => $query
                ->where('criteria_processing_status', '!=', JobCriteriaProcessingStatus::Failed)
                ->orWhere('updated_at', '>=', $this->blockedSince()))
            ->orderByDesc('updated_at')
            ->get();
    }

    /** @return Collection<int, Application> */
    private function applicationsByAnalysisStatus(Company $company): Collection
    {
        return Application::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->getKey())
            ->whereIn('analysis_status', [
                ApplicationAnalysisStatus::Pending,
                ApplicationAnalysisStatus::Processing,
                ApplicationAnalysisStatus::AwaitingCriteria,
                ApplicationAnalysisStatus::PendingQuota,
                ApplicationAnalysisStatus::Failed,
            ])
            ->where(fn ($query) => $query
                ->where('analysis_status', '!=', ApplicationAnalysisStatus::Failed)
                ->orWhere('updated_at', '>=', $this->blockedSince()))
            ->with(['job', 'candidate'])
            ->orderByDesc('updated_at')
            ->get();
    }

    /** @return Collection<int, SourcingSearch> */
    private function sourcingSearchesByStatus(Company $company): Collection
    {
        return SourcingSearch::query()
            ->where('company_id', $company->getKey())
            ->whereIn('status', [
                SourcingSearchStatus::Pending,
                SourcingSearchStatus::Processing,
                SourcingSearchStatus::PendingQuota,
                SourcingSearchStatus::Failed,
            ])
            ->where(fn ($query) => $query
                ->where('status', '!=', SourcingSearchStatus::Failed)
                ->orWhere('updated_at', '>=', $this->blockedSince()))
            ->with('job')
            ->orderByDesc('updated_at')
            ->get();
    }

    /** @return Collection<int, CandidateImportBatch> */
    private function importBatchesByStatus(Company $company): Collection
    {
        return CandidateImportBatch::query()
            ->where('company_id', $company->getKey())
            ->whereIn('status', [
                CandidateImportStatus::Validating,
                CandidateImportStatus::Processing,
                CandidateImportStatus::Paused,
                CandidateImportStatus::Failed,
            ])
            ->where(fn ($query) => $query
                ->where('status', '!=', CandidateImportStatus::Failed)
                ->orWhere('updated_at', '>=', $this->blockedSince()))
            ->orderByDesc('updated_at')
            ->get();
    }

    private function blockedSince(): CarbonImmutable
    {
        return CarbonImmutable::now()->subDays(self::BlockedWindowDays);
    }
}
