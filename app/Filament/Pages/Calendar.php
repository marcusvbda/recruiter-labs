<?php

namespace App\Filament\Pages;

use App\Data\CalendarAgendaEvent;
use App\Enums\ConnectedIntegrationStatus;
use App\Enums\InterviewStatus;
use App\Filament\Clusters\Integrations\Pages\CalendarSettings;
use App\Filament\Resources\Applications\ApplicationResource;
use App\Models\Company;
use App\Models\ConnectedIntegration;
use App\Models\Interview;
use App\Models\User;
use App\Services\GoogleCalendarAgendaClient;
use BackedEnum;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class Calendar extends Page
{
    private const string GoogleCalendarPluginKey = 'google-calendar';

    private const int AgendaCacheSeconds = 60;

    private const int DefaultFirstHour = 8;

    private const int DefaultLastHour = 19;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.calendar';

    /** Monday of the displayed week, as `Y-m-d` in the display timezone. */
    public string $weekStart = '';

    /** IANA identifier every time on this page is rendered in. */
    public string $timezone = '';

    /** Selected recruiter id as a string, or `''` for every recruiter. */
    public string $recruiterFilter = '';

    /** Whether the browser has already reported its timezone this session. */
    public bool $hasResolvedTimezone = false;

    private bool $agendaUnavailable = false;

    public static function getNavigationLabel(): string
    {
        return __('agenda.navigation_label');
    }

    public function getTitle(): string|Htmlable
    {
        return __('agenda.title');
    }

    public function mount(): void
    {
        $user = $this->getCurrentUser();

        $sessionTimezone = session('agenda.timezone');
        $this->hasResolvedTimezone = is_string($sessionTimezone) && $this->isValidTimezone($sessionTimezone);
        $this->timezone = $this->hasResolvedTimezone ? $sessionTimezone : (string) config('app.timezone');

        $this->recruiterFilter = (string) $user->getKey();
        $this->weekStart = CarbonImmutable::now($this->timezone)->startOfWeek()->format('Y-m-d');
    }

    /**
     * Called once from the browser so the grid renders in the recruiter's own
     * timezone instead of silently assuming the server's. The result is kept in
     * the session, so later visits render correctly on the first paint.
     */
    public function resolveTimezone(string $timezone): void
    {
        if ($this->hasResolvedTimezone || ! $this->isValidTimezone($timezone)) {
            $this->hasResolvedTimezone = true;

            return;
        }

        session(['agenda.timezone' => $timezone]);
        $this->timezone = $timezone;
        $this->hasResolvedTimezone = true;
        $this->weekStart = CarbonImmutable::now($timezone)->startOfWeek()->format('Y-m-d');
    }

    public function previousWeek(): void
    {
        $this->weekStart = $this->weekStartDate()->subWeek()->format('Y-m-d');
    }

    public function nextWeek(): void
    {
        $this->weekStart = $this->weekStartDate()->addWeek()->format('Y-m-d');
    }

    public function currentWeek(): void
    {
        $this->weekStart = CarbonImmutable::now($this->timezone)->startOfWeek()->format('Y-m-d');
    }

    /**
     * @return array{
     *     days: list<array{label: string, day: string, date: string, is_today: bool}>,
     *     hours: list<int>,
     *     timed_events: array<string, list<array<string, mixed>>>,
     *     all_day_events: array<string, list<array<string, mixed>>>,
     *     recruiters: array<int|string, string>,
     *     timezone: string,
     *     week_label: string,
     *     is_calendar_connected: bool,
     *     shows_own_google_events: bool,
     *     agenda_unavailable: bool,
     *     settings_url: string,
     * }
     */
    protected function getViewData(): array
    {
        $company = $this->getCompany();
        $user = $this->getCurrentUser();
        $weekStart = $this->weekStartDate();
        $weekEnd = $weekStart->addWeek();

        $events = $this->agendaEvents($company, $user, $weekStart, $weekEnd);
        $timedEvents = array_values(array_filter($events, fn (CalendarAgendaEvent $event): bool => ! $event->isAllDay));

        return [
            'days' => $this->days($weekStart),
            'hours' => $this->hourRange($timedEvents),
            'timed_events' => $this->groupByDay($timedEvents),
            'all_day_events' => $this->groupByDay(array_values(array_filter(
                $events,
                fn (CalendarAgendaEvent $event): bool => $event->isAllDay,
            ))),
            'recruiters' => $this->recruiterOptions($company),
            'timezone' => $this->timezone,
            'week_label' => $this->weekLabel($weekStart),
            'is_calendar_connected' => $this->isCalendarConnected($company, $user),
            'shows_own_google_events' => $this->showsOwnGoogleEvents($user),
            'agenda_unavailable' => $this->agendaUnavailable,
            'settings_url' => CalendarSettings::getUrl(tenant: $company),
        ];
    }

    /** @return list<CalendarAgendaEvent> */
    private function agendaEvents(Company $company, User $user, CarbonImmutable $weekStart, CarbonImmutable $weekEnd): array
    {
        $interviews = $this->interviewEvents($company, $weekStart, $weekEnd);
        $events = $interviews
            ->map(fn (Interview $interview): CalendarAgendaEvent => $this->interviewAgendaEvent($interview, $company))
            ->values()
            ->all();

        if ($this->showsOwnGoogleEvents($user) && $this->isCalendarConnected($company, $user)) {
            $interviewEventIds = $interviews->pluck('calendar_event_id')->filter()->all();

            foreach ($this->googleEvents($company, $user, $weekStart, $weekEnd) as $googleEvent) {
                // Interview events already came from the database — including the
                // Google copy would render the same interview twice.
                if (in_array($googleEvent['id'], $interviewEventIds, true)) {
                    continue;
                }

                $events[] = new CalendarAgendaEvent(
                    key: 'google-'.$googleEvent['id'],
                    title: $googleEvent['summary'] ?? __('agenda.untitled_event'),
                    subtitle: null,
                    startsAt: CarbonImmutable::parse($googleEvent['starts_at'])->setTimezone($this->timezone),
                    endsAt: CarbonImmutable::parse($googleEvent['ends_at'])->setTimezone($this->timezone),
                    isAllDay: $googleEvent['is_all_day'],
                    isInterview: false,
                    url: $googleEvent['html_link'],
                );
            }
        }

        usort($events, fn (CalendarAgendaEvent $a, CalendarAgendaEvent $b): int => $a->startsAt <=> $b->startsAt);

        return $events;
    }

    /** @return Collection<int, Interview> */
    private function interviewEvents(Company $company, CarbonImmutable $weekStart, CarbonImmutable $weekEnd): Collection
    {
        return Interview::query()
            ->whereBelongsTo($company)
            ->with(['application.candidate', 'application.job'])
            ->where('status', '!=', InterviewStatus::Cancelled->value)
            ->when(
                $this->selectedRecruiterId() !== null,
                fn ($query) => $query->where('calendar_user_id', $this->selectedRecruiterId()),
            )
            ->where('scheduled_at', '<', $weekEnd)
            ->where('ends_at', '>', $weekStart)
            ->orderBy('scheduled_at')
            ->get();
    }

    private function interviewAgendaEvent(Interview $interview, Company $company): CalendarAgendaEvent
    {
        return new CalendarAgendaEvent(
            key: 'interview-'.$interview->getKey(),
            title: $interview->application->candidate->name,
            subtitle: $interview->application->job->name,
            startsAt: $interview->scheduled_at->setTimezone($this->timezone),
            endsAt: $interview->ends_at->setTimezone($this->timezone),
            isAllDay: false,
            isInterview: true,
            url: ApplicationResource::getUrl('view', ['record' => $interview->application], tenant: $company),
            badge: __('applications.admin.interviews.rsvp.'.$interview->rsvp_status->value),
        );
    }

    /**
     * @return list<array{id: string, summary: string|null, starts_at: string, ends_at: string, is_all_day: bool, html_link: string|null}>
     */
    private function googleEvents(Company $company, User $user, CarbonImmutable $weekStart, CarbonImmutable $weekEnd): array
    {
        $cacheKey = implode(':', [
            'agenda-google-events',
            $company->getKey(),
            $user->getKey(),
            $weekStart->format('Y-m-d'),
            $this->timezone,
        ]);

        try {
            return Cache::remember(
                $cacheKey,
                self::AgendaCacheSeconds,
                fn (): array => app(GoogleCalendarAgendaClient::class)->listEvents($company, $user, $weekStart, $weekEnd),
            );
        } catch (\Throwable) {
            // The recruiter's own interviews still render; the banner explains
            // that the external part of the agenda could not be loaded.
            $this->agendaUnavailable = true;

            return [];
        }
    }

    /**
     * @param  list<CalendarAgendaEvent>  $events
     * @return array<string, list<array<string, mixed>>>
     */
    private function groupByDay(array $events): array
    {
        $grouped = [];

        foreach ($events as $event) {
            $day = $event->startsAt->format('Y-m-d');
            $grouped[$day][] = [
                'key' => $event->key,
                'title' => $event->title,
                'subtitle' => $event->subtitle,
                'badge' => $event->badge,
                'url' => $event->url,
                'is_interview' => $event->isInterview,
                'time_label' => $event->startsAt->format('H:i').'–'.$event->endsAt->format('H:i'),
                'start_minutes' => $event->startsAt->hour * 60 + $event->startsAt->minute,
                'duration_minutes' => max(30, (int) $event->startsAt->diffInMinutes($event->endsAt)),
            ];
        }

        return array_map($this->assignLanes(...), $grouped);
    }

    /**
     * Side-by-side placement for events sharing a slot. Without it, two
     * interviews at the same hour would be drawn on top of each other and only
     * the last one painted would be readable.
     *
     * @param  list<array<string, mixed>>  $dayEvents
     * @return list<array<string, mixed>>
     */
    private function assignLanes(array $dayEvents): array
    {
        /** @var list<int> $laneEndMinutes */
        $laneEndMinutes = [];

        foreach ($dayEvents as $index => $event) {
            $startMinutes = (int) $event['start_minutes'];
            $endMinutes = $startMinutes + (int) $event['duration_minutes'];
            $lane = null;

            foreach ($laneEndMinutes as $candidateLane => $laneEnd) {
                if ($laneEnd <= $startMinutes) {
                    $lane = $candidateLane;
                    break;
                }
            }

            if ($lane === null) {
                $lane = count($laneEndMinutes);
            }

            $laneEndMinutes[$lane] = $endMinutes;
            $dayEvents[$index]['lane'] = $lane;
        }

        $laneCount = max(1, count($laneEndMinutes));

        return array_map(function (array $event) use ($laneCount): array {
            $event['lane_count'] = $laneCount;

            return $event;
        }, $dayEvents);
    }

    /**
     * @param  list<CalendarAgendaEvent>  $timedEvents
     * @return list<int>
     */
    private function hourRange(array $timedEvents): array
    {
        $firstHour = self::DefaultFirstHour;
        $lastHour = self::DefaultLastHour;

        foreach ($timedEvents as $event) {
            $firstHour = min($firstHour, $event->startsAt->hour);
            $lastHour = max($lastHour, $event->endsAt->minute > 0 ? $event->endsAt->hour + 1 : $event->endsAt->hour);
        }

        return range(max(0, $firstHour), min(24, max($lastHour, $firstHour + 1)) - 1);
    }

    /** @return list<array{label: string, day: string, date: string, is_today: bool}> */
    private function days(CarbonImmutable $weekStart): array
    {
        $today = CarbonImmutable::now($this->timezone)->format('Y-m-d');

        return array_map(function (int $offset) use ($weekStart, $today): array {
            $day = $weekStart->addDays($offset);

            return [
                'label' => $day->translatedFormat('D'),
                'day' => $day->translatedFormat('j M'),
                'date' => $day->format('Y-m-d'),
                'is_today' => $day->format('Y-m-d') === $today,
            ];
        }, range(0, 6));
    }

    private function weekLabel(CarbonImmutable $weekStart): string
    {
        $weekEnd = $weekStart->addDays(6);

        return $weekStart->translatedFormat('j M').' – '.$weekEnd->translatedFormat('j M Y');
    }

    /** @return array<int|string, string> */
    private function recruiterOptions(Company $company): array
    {
        $options = ['' => __('agenda.filters.all_recruiters')];

        foreach ($company->users()->orderBy('name')->get() as $recruiter) {
            $options[(int) $recruiter->getKey()] = $recruiter->name;
        }

        return $options;
    }

    /**
     * Personal Google events belong to the signed-in recruiter only — another
     * recruiter's private calendar is never fetched on their behalf.
     */
    private function showsOwnGoogleEvents(User $user): bool
    {
        $selectedRecruiterId = $this->selectedRecruiterId();

        return $selectedRecruiterId === null || $selectedRecruiterId === (int) $user->getKey();
    }

    private function selectedRecruiterId(): ?int
    {
        return $this->recruiterFilter === '' ? null : (int) $this->recruiterFilter;
    }

    private function isCalendarConnected(Company $company, User $user): bool
    {
        return ConnectedIntegration::query()
            ->whereBelongsTo($company)
            ->whereBelongsTo($user)
            ->where('plugin_key', self::GoogleCalendarPluginKey)
            ->value('status') === ConnectedIntegrationStatus::Connected;
    }

    private function weekStartDate(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $this->weekStart, $this->timezone)->startOfDay();
    }

    private function isValidTimezone(string $timezone): bool
    {
        return in_array($timezone, DateTimeZone::listIdentifiers(), true);
    }

    private function getCompany(): Company
    {
        $company = Filament::getTenant();

        abort_unless($company instanceof Company, 404);

        return $company;
    }

    private function getCurrentUser(): User
    {
        $user = Filament::auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
