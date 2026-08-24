<?php

namespace App\Services;

use App\Data\RecruitmentAttentionItem;
use App\Data\RecruitmentAttentionQueue;
use App\Enums\ApplicationAnalysisStatus;
use App\Enums\ConnectedIntegrationStatus;
use App\Enums\InterviewCalendarSyncStatus;
use App\Enums\InterviewRsvpStatus;
use App\Enums\RecruitmentAttentionType;
use App\Filament\Clusters\Settings\Pages\AiSettings;
use App\Filament\Clusters\Settings\Pages\CalendarSettings;
use App\Filament\Resources\Applications\ApplicationResource;
use App\Filament\Resources\Jobs\JobResource;
use App\Models\Application;
use App\Models\Company;
use App\Models\ConnectedIntegration;
use App\Models\Interview;
use App\Models\Job;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Answers "what needs this recruiter's attention right now?".
 *
 * Everything here is *derived*: an item exists because a row in the database
 * says so, and disappears the moment that stops being true. There is no task
 * table, no dismiss flag, and deliberately no model call — a recruiter must be
 * able to read the reason for every item and disagree with it.
 *
 * Nothing in this service performs a recruitment action. It ranks work and links
 * to where a human does it.
 *
 * Scope rules, chosen so the queue neither lies nor hides real work:
 *
 * - Interview items are **personal**: only interviews owned by the signed-in
 *   recruiter's calendar, matching what the overview promises elsewhere.
 * - Application items cover every **published** job, including jobs whose
 *   campaign window has closed — a finalist waiting for a decision does not stop
 *   waiting because the advert expired.
 * - Job items cover **currently active** processes only, since the suggestions
 *   they make (pause intake, review the target) apply to a live campaign.
 */
class RecruitmentAttentionService
{
    /** How close a campaign's end date has to be before it is worth raising. */
    public const JobEndingSoonDays = 7;

    /**
     * Items shown per signal. A queue nobody can read is not a queue; the ones
     * left out stay counted in {@see RecruitmentAttentionQueue::hiddenCount()}
     * rather than silently disappearing.
     */
    private const MaxItemsPerSignal = 5;

    public function for(Company $company, User $recruiter): RecruitmentAttentionQueue
    {
        return $this->build($company, $recruiter, null);
    }

    /** The same derivation, narrowed to one hiring process. */
    public function forJob(Job $job, User $recruiter): RecruitmentAttentionQueue
    {
        $company = $job->company;

        if (! $company instanceof Company) {
            return RecruitmentAttentionQueue::empty();
        }

        return $this->build($company, $recruiter, $job);
    }

    public function countFor(Company $company, User $recruiter): int
    {
        return $this->for($company, $recruiter)->total;
    }

    private function build(Company $company, User $recruiter, ?Job $job): RecruitmentAttentionQueue
    {
        /** @var list<array{items: list<RecruitmentAttentionItem>, total: int}> $signals */
        $signals = [
            ...$this->interviewSignals($company, $recruiter, $job),
            ...$this->applicationSignals($company, $job),
            ...$this->jobSignals($company, $job),
        ];

        $total = 0;
        /** @var Collection<int, RecruitmentAttentionItem> $items */
        $items = new Collection;

        foreach ($signals as $signal) {
            $total += $signal['total'];
            $items = $items->concat($signal['items']);
        }

        return new RecruitmentAttentionQueue(
            $items
                ->sortBy(fn (RecruitmentAttentionItem $item): int => $item->severity()->weight())
                ->values(),
            $total,
        );
    }

    /**
     * Commitments the recruiter personally owns that are no longer safe: the
     * candidate said no, or the calendar never got the event.
     *
     * @return list<array{items: list<RecruitmentAttentionItem>, total: int}>
     */
    private function interviewSignals(Company $company, User $recruiter, ?Job $job): array
    {
        $jobId = $job?->getKey();
        $interviews = Interview::query()
            ->whereBelongsTo($company)
            ->upcoming()
            ->where('calendar_user_id', $recruiter->getKey())
            ->when($jobId !== null, fn (Builder $query): Builder => $query->whereHas(
                'application',
                fn (Builder $applications): Builder => $applications->where('job_id', $jobId),
            ))
            ->with(['application.candidate', 'application.job'])
            ->orderBy('scheduled_at')
            ->get();

        $declined = [];
        $calendarFailed = [];

        foreach ($interviews as $interview) {
            $application = $interview->application;

            if (! $application instanceof Application) {
                continue;
            }

            $candidate = $application->candidate->name;
            $when = $interview->scheduled_at->setTimezone($interview->timezone)->translatedFormat('M j, Y · H:i');

            if ($interview->rsvp_status === InterviewRsvpStatus::Declined) {
                $declined[] = new RecruitmentAttentionItem(
                    type: RecruitmentAttentionType::InterviewDeclined,
                    title: (string) __('attention.items.interview_declined.title', ['candidate' => $candidate]),
                    explanation: (string) __('attention.items.interview_declined.explanation', ['date' => $when]),
                    actionLabel: (string) __('attention.items.interview_declined.action'),
                    actionUrl: $this->applicationUrl($application, 'interviews'),
                    context: $application->job->name,
                    jobId: (int) $application->job_id,
                    applicationId: (int) $application->getKey(),
                    interviewId: (int) $interview->getKey(),
                );
            }

            if ($this->hasCalendarProblem($interview)) {
                $calendarFailed[] = new RecruitmentAttentionItem(
                    type: RecruitmentAttentionType::InterviewCalendarFailed,
                    title: (string) __('attention.items.interview_calendar_failed.title', ['candidate' => $candidate]),
                    explanation: (string) __('attention.items.interview_calendar_failed.explanation', ['date' => $when]),
                    actionLabel: (string) __('attention.items.interview_calendar_failed.action'),
                    actionUrl: $this->applicationUrl($application, 'interviews'),
                    context: $application->job->name,
                    jobId: (int) $application->job_id,
                    applicationId: (int) $application->getKey(),
                    interviewId: (int) $interview->getKey(),
                );
            }
        }

        return [
            $this->cap($declined),
            $this->cap($calendarFailed),
            $this->cap($this->calendarReconnectSignal($company, $recruiter, $interviews->count())),
        ];
    }

    /**
     * The interview is recorded here but the calendar either refused the event or
     * gave up retrying, which means the candidate may hold no invitation at all.
     */
    private function hasCalendarProblem(Interview $interview): bool
    {
        return $interview->calendar_sync_terminal
            || $interview->calendar_sync_status === InterviewCalendarSyncStatus::Failed;
    }

    /**
     * Raised only when it actually affects recruitment: a stale calendar grant
     * with no interviews behind it is a settings detail, not attention.
     *
     * @return list<RecruitmentAttentionItem>
     */
    private function calendarReconnectSignal(Company $company, User $recruiter, int $upcomingInterviewCount): array
    {
        if ($upcomingInterviewCount === 0) {
            return [];
        }

        $needsReconnection = ConnectedIntegration::query()
            ->whereBelongsTo($company)
            ->whereBelongsTo($recruiter)
            ->where('plugin_key', 'google-calendar')
            ->where('status', ConnectedIntegrationStatus::ReauthorizationRequired->value)
            ->exists();

        if (! $needsReconnection) {
            return [];
        }

        return [
            new RecruitmentAttentionItem(
                type: RecruitmentAttentionType::CalendarReconnectRequired,
                title: (string) __('attention.items.calendar_reconnect_required.title'),
                explanation: trans_choice(
                    'attention.items.calendar_reconnect_required.explanation',
                    $upcomingInterviewCount,
                    ['count' => $upcomingInterviewCount],
                ),
                actionLabel: (string) __('attention.items.calendar_reconnect_required.action'),
                actionUrl: CalendarSettings::getUrl(tenant: $company),
            ),
        ];
    }

    /**
     * Candidates the process is holding up: waiting past their stage's own
     * expectation, sitting in a final stage with nothing booked, or carrying an
     * evaluation that never completed.
     *
     * @return list<array{items: list<RecruitmentAttentionItem>, total: int}>
     */
    private function applicationSignals(Company $company, ?Job $job): array
    {
        $jobId = $job?->getKey();
        $base = fn (): Builder => Application::query()
            ->whereBelongsTo($company)
            ->when(
                $jobId !== null,
                fn (Builder $query): Builder => $query->where('job_id', $jobId),
                fn (Builder $query): Builder => $query->whereHas(
                    'job',
                    fn (Builder $jobs): Builder => $jobs->where('published', true),
                ),
            )
            ->with(['candidate', 'job', 'status']);

        // A finalist with nothing booked already raises "decision pending", which
        // is the same recruiter decision described more precisely. Raising
        // "waiting too long" alongside it would ask twice for one action, so the
        // more specific signal wins and the age one steps aside.
        [$overdue, $overdueTotal] = $this->bounded(
            $base()
                ->overdueInStage($company)
                ->whereNot(fn (Builder $query): Builder => $query
                    ->inFinalStage()
                    ->whereDoesntHave('upcomingInterviews'))
                ->orderBy('status_entered_at'),
        );

        [$awaitingDecision, $awaitingDecisionTotal] = $this->bounded(
            $base()
                ->inFinalStage()
                ->whereDoesntHave('upcomingInterviews')
                ->orderBy('status_entered_at'),
        );

        [$failedEvaluations, $failedEvaluationsTotal] = $this->bounded(
            $base()
                ->inProcess()
                ->where('analysis_status', ApplicationAnalysisStatus::Failed->value)
                ->orderByDesc('updated_at'),
        );

        return [
            [
                'items' => array_values($overdue
                    ->map(fn (Application $application): RecruitmentAttentionItem => $this->stageOverdueItem($application))
                    ->all()),
                'total' => $overdueTotal,
            ],
            [
                'items' => array_values($awaitingDecision
                    ->map(fn (Application $application): RecruitmentAttentionItem => $this->decisionPendingItem($application))
                    ->all()),
                'total' => $awaitingDecisionTotal,
            ],
            [
                'items' => array_values($failedEvaluations
                    ->map(fn (Application $application): RecruitmentAttentionItem => $this->evaluationFailedItem($application))
                    ->all()),
                'total' => $failedEvaluationsTotal,
            ],
            $this->cap($this->quotaBlockedSignal($company, $base())),
        ];
    }

    private function stageOverdueItem(Application $application): RecruitmentAttentionItem
    {
        $days = $application->daysInCurrentStage();
        $threshold = (int) $application->status->attention_after_days;

        return new RecruitmentAttentionItem(
            type: RecruitmentAttentionType::StageOverdue,
            title: (string) __('attention.items.stage_overdue.title', [
                'candidate' => $application->candidate->name,
                'stage' => $application->status->name,
            ]),
            explanation: (string) __('attention.items.stage_overdue.explanation', [
                'waited' => $this->days($days),
                'stage' => $application->status->name,
                'threshold' => $this->days($threshold),
            ]),
            actionLabel: (string) __('attention.items.stage_overdue.action'),
            actionUrl: $this->applicationUrl($application),
            context: $application->job->name,
            jobId: (int) $application->job_id,
            applicationId: (int) $application->getKey(),
        );
    }

    private function decisionPendingItem(Application $application): RecruitmentAttentionItem
    {
        return new RecruitmentAttentionItem(
            type: RecruitmentAttentionType::DecisionPending,
            title: (string) __('attention.items.decision_pending.title', [
                'candidate' => $application->candidate->name,
            ]),
            explanation: (string) __('attention.items.decision_pending.explanation', [
                'stage' => $application->status->name,
                'waited' => $this->days($application->daysInCurrentStage()),
            ]),
            actionLabel: (string) __('attention.items.decision_pending.action'),
            actionUrl: $this->applicationUrl($application),
            context: $application->job->name,
            jobId: (int) $application->job_id,
            applicationId: (int) $application->getKey(),
        );
    }

    private function evaluationFailedItem(Application $application): RecruitmentAttentionItem
    {
        return new RecruitmentAttentionItem(
            type: RecruitmentAttentionType::EvaluationFailed,
            title: (string) __('attention.items.evaluation_failed.title', [
                'candidate' => $application->candidate->name,
            ]),
            explanation: (string) __('attention.items.evaluation_failed.explanation'),
            actionLabel: (string) __('attention.items.evaluation_failed.action'),
            actionUrl: $this->applicationUrl($application, 'evaluation'),
            context: $application->job->name,
            jobId: (int) $application->job_id,
            applicationId: (int) $application->getKey(),
        );
    }

    /**
     * Aggregated on purpose: the blocker is one workspace-wide allowance, so one
     * item pointing at the allowance beats one item per stuck candidate.
     *
     * @param  Builder<Application>  $applications
     * @return list<RecruitmentAttentionItem>
     */
    private function quotaBlockedSignal(Company $company, Builder $applications): array
    {
        $blocked = $applications
            ->inProcess()
            ->where('analysis_status', ApplicationAnalysisStatus::PendingQuota->value)
            ->count();

        if ($blocked === 0) {
            return [];
        }

        return [
            new RecruitmentAttentionItem(
                type: RecruitmentAttentionType::EvaluationBlockedByQuota,
                title: (string) __('attention.items.evaluation_blocked_by_quota.title'),
                explanation: trans_choice(
                    'attention.items.evaluation_blocked_by_quota.explanation',
                    $blocked,
                    ['count' => $blocked],
                ),
                actionLabel: (string) __('attention.items.evaluation_blocked_by_quota.action'),
                actionUrl: AiSettings::getUrl(tenant: $company),
            ),
        ];
    }

    /**
     * Processes that are not going anywhere, are about to run out of time, or
     * have already achieved what they set out to achieve.
     *
     * Reaching the target only *suggests* the next step: nothing here pauses,
     * unpublishes or closes a job.
     *
     * @return list<array{items: list<RecruitmentAttentionItem>, total: int}>
     */
    private function jobSignals(Company $company, ?Job $job): array
    {
        $jobId = $job?->getKey();
        $jobs = Job::query()
            ->whereBelongsTo($company)
            ->when($jobId !== null, fn (Builder $query): Builder => $query->whereKey($jobId))
            ->currentlyActive()
            ->withCount(RecruitmentProgressService::ProgressCounts)
            ->orderBy('name')
            ->get();

        $endsSoonBefore = CarbonImmutable::now()->addDays(self::JobEndingSoonDays)->endOfDay();
        $stalled = [];
        $endingWithoutFinalists = [];
        $targetReached = [];
        $targetNear = [];

        foreach ($jobs as $activeJob) {
            $applications = (int) $activeJob->getAttribute('applications_count');
            $interviewing = (int) $activeJob->getAttribute('interviewing_applications_count');
            $finalists = (int) $activeJob->getAttribute('final_stage_applications_count');
            $hired = (int) $activeJob->getAttribute('hired_applications_count');
            $overdue = (int) $activeJob->getAttribute('overdue_applications_count');
            $target = max(1, $activeJob->hiring_target);
            $endsAt = $activeJob->ends_at;
            $endingWithoutFinalistsApplies = $endsAt !== null
                && $endsAt->lessThanOrEqualTo($endsSoonBefore)
                && $finalists === 0
                && $hired === 0;

            // "Stalled" now needs actual waiting evidence behind it. Candidates
            // arriving, nobody being interviewed yet, and the job being new are
            // all normal early states — calling that stalled trains recruiters to
            // ignore the queue. What makes it real is candidates sitting past the
            // threshold their own stage declares, with nothing having moved
            // forward. Where the campaign is also about to end, that signal says
            // it better, so this one stands down.
            if ($overdue > 0 && $interviewing === 0 && $finalists === 0 && $hired === 0 && ! $endingWithoutFinalistsApplies) {
                $stalled[] = new RecruitmentAttentionItem(
                    type: RecruitmentAttentionType::JobStalled,
                    title: (string) __('attention.items.job_stalled.title', ['job' => $activeJob->name]),
                    explanation: trans_choice('attention.items.job_stalled.explanation', $overdue, [
                        'count' => $overdue,
                        'applications' => $applications,
                    ]),
                    actionLabel: (string) __('attention.items.job_stalled.action'),
                    actionUrl: $this->jobUrl($activeJob, 'pipeline'),
                    context: $activeJob->name,
                    jobId: (int) $activeJob->getKey(),
                );
            }

            if ($endingWithoutFinalistsApplies) {
                $endingWithoutFinalists[] = new RecruitmentAttentionItem(
                    type: RecruitmentAttentionType::JobEndingWithoutFinalists,
                    title: (string) __('attention.items.job_ending_without_finalists.title', ['job' => $activeJob->name]),
                    explanation: (string) __('attention.items.job_ending_without_finalists.explanation', [
                        'date' => $endsAt->translatedFormat('M j, Y'),
                    ]),
                    actionLabel: (string) __('attention.items.job_ending_without_finalists.action'),
                    actionUrl: $this->jobUrl($activeJob),
                    context: $activeJob->name,
                    jobId: (int) $activeJob->getKey(),
                );
            }

            if ($hired >= $target) {
                $targetReached[] = new RecruitmentAttentionItem(
                    type: RecruitmentAttentionType::HiringTargetReached,
                    title: (string) __('attention.items.hiring_target_reached.title', ['job' => $activeJob->name]),
                    explanation: (string) __('attention.items.hiring_target_reached.explanation', [
                        'hired' => $hired,
                        'target' => $target,
                    ]),
                    actionLabel: (string) __('attention.items.hiring_target_reached.action'),
                    actionUrl: $this->jobUrl($activeJob),
                    context: $activeJob->name,
                    jobId: (int) $activeJob->getKey(),
                );

                continue;
            }

            if ($target > 1 && $target - $hired === 1) {
                $targetNear[] = new RecruitmentAttentionItem(
                    type: RecruitmentAttentionType::HiringTargetNear,
                    title: (string) __('attention.items.hiring_target_near.title', ['job' => $activeJob->name]),
                    explanation: (string) __('attention.items.hiring_target_near.explanation', [
                        'hired' => $hired,
                        'target' => $target,
                    ]),
                    actionLabel: (string) __('attention.items.hiring_target_near.action'),
                    actionUrl: $this->jobUrl($activeJob, 'pipeline'),
                    context: $activeJob->name,
                    jobId: (int) $activeJob->getKey(),
                );
            }
        }

        return [
            $this->cap($targetReached),
            $this->cap($endingWithoutFinalists),
            $this->cap($stalled),
            $this->cap($targetNear),
        ];
    }

    /**
     * @param  list<RecruitmentAttentionItem>  $items
     * @return array{items: list<RecruitmentAttentionItem>, total: int}
     */
    private function cap(array $items): array
    {
        return [
            'items' => array_slice($items, 0, self::MaxItemsPerSignal),
            'total' => count($items),
        ];
    }

    /**
     * Reads at most one page of a signal, and only pays for a `count()` when the
     * page came back full — otherwise the page *is* the count.
     *
     * @param  Builder<Application>  $query
     * @return array{\Illuminate\Database\Eloquent\Collection<int, Application>, int}
     */
    private function bounded(Builder $query): array
    {
        $records = (clone $query)->limit(self::MaxItemsPerSignal)->get();
        $total = $records->count() < self::MaxItemsPerSignal
            ? $records->count()
            : (clone $query)->count();

        return [$records, $total];
    }

    private function days(int $days): string
    {
        return trans_choice('attention.days', $days, ['count' => $days]);
    }

    private function applicationUrl(Application $application, ?string $section = null): string
    {
        return ApplicationResource::getUrl(
            'view',
            array_filter(['record' => $application, 'section' => $section]),
            tenant: $application->company,
        );
    }

    private function jobUrl(Job $job, ?string $section = null): string
    {
        return JobResource::getUrl(
            'view',
            array_filter(['record' => $job, 'section' => $section]),
            tenant: $job->company,
        );
    }
}
