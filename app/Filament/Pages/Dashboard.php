<?php

namespace App\Filament\Pages;

use App\Data\RecruiterAgendaPreview;
use App\Data\WorkspaceActivationProgress;
use App\Filament\Resources\Jobs\JobResource;
use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use App\Services\RecruitmentAttentionService;
use App\Services\RecruitmentProgressService;
use App\Services\WorkspaceActivationJourney;
use BackedEnum;
use DateTimeZone;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The operational answer to "what needs my attention?". It is deliberately not
 * a welcome page: no greeting, no workspace identity, no decorative hero.
 *
 * It is also not a stack of widgets. The page composes three regions itself, in
 * the order the product promises: what needs attention, the commitments the
 * recruiter has to keep today, and how the live hiring processes are moving.
 * Attention leads the layout, the agenda sits beside it as personal context,
 * and the totals are one quiet line — a number describes the workspace, the
 * queue tells the recruiter what to do about it.
 *
 * Every figure on this page is read from the services that own its meaning
 * ({@see RecruitmentAttentionService}, {@see RecruitmentProgressService}); the
 * page only decides what is worth showing and in what order.
 */
class Dashboard extends BaseDashboard
{
    /**
     * How much of each region is worth reading in one glance. Anything past the
     * cap stays counted — the regions say how much is not listed rather than
     * pretending the list is complete.
     */
    private const int AttentionLimit = 6;

    private const int AgendaLimit = 6;

    private const int ProcessLimit = 6;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.dashboard';

    public static function getNavigationLabel(): string
    {
        return __('dashboard.navigation_label');
    }

    public function getTitle(): string|Htmlable
    {
        return __('dashboard.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('dashboard.subtitle');
    }

    /**
     * @return array{
     *     attention: list<array<string, int|string|null>>,
     *     attention_total: int,
     *     attention_hidden: int,
     *     agenda: RecruiterAgendaPreview,
     *     processes: list<array<string, mixed>>,
     *     processes_hidden: int,
     *     summary: list<array{key: string, value: int, url: string}>,
     *     jobs_url: string,
     *     calendar_url: string,
     *     activation: WorkspaceActivationProgress|null,
     *     show_welcome: bool,
     * }
     */
    protected function getViewData(): array
    {
        $company = Filament::getTenant();
        $recruiter = Filament::auth()->user();

        $data = [
            'attention' => [],
            'attention_total' => 0,
            'attention_hidden' => 0,
            'agenda' => RecruiterAgendaPreview::empty($this->displayTimezone()),
            'processes' => [],
            'processes_hidden' => 0,
            'summary' => [],
            'jobs_url' => JobResource::getUrl(),
            'calendar_url' => Calendar::getUrl(),
            'activation' => null,
            'show_welcome' => false,
        ];

        if (! $company instanceof Company || ! $recruiter instanceof User) {
            return $data;
        }

        $progress = app(RecruitmentProgressService::class);
        $summary = $progress->workspaceSummary($company, $recruiter);
        $queue = app(RecruitmentAttentionService::class)->for($company, $recruiter);
        $listedAttention = array_slice($queue->toArray(), 0, self::AttentionLimit);
        // Read once here so the view only ever composes; while the
        // workspace is activated this stays unused and the Overview
        // renders exactly as it did before the journey existed (AC27).
        $activation = app(WorkspaceActivationJourney::class)->for($company, $recruiter);

        return [
            ...$data,
            'attention' => $listedAttention,
            'attention_total' => $queue->total,
            'attention_hidden' => max(0, $queue->total - count($listedAttention)),
            'agenda' => $this->agenda($company, $recruiter, $progress, $summary['upcoming_interviews']),
            'processes' => $this->processes($company, $progress),
            'processes_hidden' => max(0, $summary['active_jobs'] - self::ProcessLimit),
            'summary' => $this->summaryFigures($summary),
            'activation' => $activation,
            // Eligible only while there is a journey left to introduce and
            // this member has not already said "later" for this workspace
            // (T04's per-user pivot timestamp) — never a milestone check.
            'show_welcome' => ! $activation->isActivated() && ! $company->hasDismissedOnboardingWelcome($recruiter),
        ];
    }

    /**
     * "Get started" takes the user straight to the one useful next action and
     * also records the personal welcome dismissal (T04), so navigating back to
     * the Overview mid-journey does not resurface the same introduction on
     * every visit. The persistent way back into the journey after this is the
     * checklist below, not the welcome modal.
     */
    public function startOnboardingWelcome(): void
    {
        $company = Filament::getTenant();
        $recruiter = Filament::auth()->user();

        if (! $company instanceof Company || ! $recruiter instanceof User) {
            return;
        }

        $company->dismissOnboardingWelcomeFor($recruiter);

        $nextStepUrl = app(WorkspaceActivationJourney::class)->for($company, $recruiter)->nextStep()['url'] ?? null;

        if (is_string($nextStepUrl)) {
            $this->redirect($nextStepUrl, navigate: true);
        }
    }

    /**
     * "Continue later" only stamps the personal dismissal timestamp (T04). It
     * writes no milestone and reads no milestone — activation progress is
     * completely unaffected (AC20).
     */
    public function dismissOnboardingWelcome(): void
    {
        $company = Filament::getTenant();
        $recruiter = Filament::auth()->user();

        if (! $company instanceof Company || ! $recruiter instanceof User) {
            return;
        }

        $company->dismissOnboardingWelcomeFor($recruiter);
    }

    /**
     * The volume currently in play, as one line rather than four cards: the
     * regions above already say what is happening and what to do about it, so
     * these figures only orient. Each one still leads to the page where that
     * work is done.
     *
     * @param  array<string, int>  $summary
     * @return list<array{key: string, value: int, url: string}>
     */
    private function summaryFigures(array $summary): array
    {
        $jobsUrl = JobResource::getUrl();

        return [
            ['key' => 'active_jobs', 'value' => $summary['active_jobs'], 'url' => $jobsUrl],
            ['key' => 'active_applications', 'value' => $summary['active_applications'], 'url' => $jobsUrl],
            ['key' => 'finalists', 'value' => $summary['finalists'], 'url' => $this->jobsProgressUrl('finalists')],
            ['key' => 'hired', 'value' => $summary['hired'], 'url' => $this->jobsProgressUrl('hired')],
        ];
    }

    private function jobsProgressUrl(string $progressFilter): string
    {
        return JobResource::getUrl(parameters: ['tableFilters[progress][value]' => $progressFilter]);
    }

    /**
     * The recruiter's own commitments. Ownership stays where it is defined: the
     * progress service narrows the query to interviews on this recruiter's
     * calendar, and another recruiter's agenda is never shown here as theirs.
     */
    private function agenda(
        Company $company,
        User $recruiter,
        RecruitmentProgressService $progress,
        int $total,
    ): RecruiterAgendaPreview {
        $interviews = $progress
            ->upcomingInterviewsQuery($company, $recruiter)
            ->with(['application.candidate', 'application.job'])
            ->limit(self::AgendaLimit)
            ->get();

        return RecruiterAgendaPreview::forInterviews($interviews, $this->displayTimezone(), $total);
    }

    /**
     * Active hiring processes and how far each one has actually moved, so a
     * recruiter can spot the stalled ones without opening every job. A paused
     * job is still an active process: its candidates are still being
     * interviewed.
     *
     * @return list<array<string, mixed>>
     */
    private function processes(Company $company, RecruitmentProgressService $progress): array
    {
        return array_values(Job::query()
            ->whereBelongsTo($company)
            ->currentlyActive()
            ->withCount(RecruitmentProgressService::ProgressCounts)
            ->orderByDesc('created_at')
            ->limit(self::ProcessLimit)
            ->get()
            ->map(fn (Job $job): array => [
                'name' => $job->name,
                'url' => JobResource::getWorkspaceUrl($job),
                'progress' => $progress->forJob($job),
                'is_stalled' => $progress->isStalled($job),
                'is_paused' => $job->applications_paused,
            ])
            ->all());
    }

    /**
     * The timezone the agenda already resolved from this recruiter's browser,
     * falling back to the application's. Every time on this page means the same
     * clock, so it is stated once instead of on every row.
     */
    private function displayTimezone(): string
    {
        $sessionTimezone = session('agenda.timezone');

        return is_string($sessionTimezone) && in_array($sessionTimezone, DateTimeZone::listIdentifiers(), true)
            ? $sessionTimezone
            : (string) config('app.timezone');
    }
}
