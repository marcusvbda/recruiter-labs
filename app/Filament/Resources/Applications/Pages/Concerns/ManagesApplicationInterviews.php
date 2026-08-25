<?php

namespace App\Filament\Resources\Applications\Pages\Concerns;

use App\Actions\CancelInterview;
use App\Actions\RescheduleInterview;
use App\Actions\ScheduleInterview;
use App\Actions\SyncInterviewResponse;
use App\Enums\ConnectedIntegrationStatus;
use App\Enums\InterviewCalendarSyncStatus;
use App\Enums\InterviewStatus;
use App\Exceptions\ConnectedIntegrationReauthorizationRequired;
use App\Exceptions\InterviewCalendarOperationUnavailable;
use App\Exceptions\InterviewCalendarTerminalFailure;
use App\Filament\Clusters\Settings\Pages\CalendarSettings;
use App\Filament\Resources\Applications\ApplicationResource;
use App\Models\Application;
use App\Models\ApplicationInterviewBriefItem;
use App\Models\Company;
use App\Models\ConnectedIntegration;
use App\Models\Interview;
use App\Models\InterviewFeedback;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use DateTimeZone;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use LogicException;

trait ManagesApplicationInterviews
{
    /**
     * The name is a parameter because the same action is offered twice on the
     * application page — in the header and, when it is the recommended next
     * step, inside the summary. Filament mounts actions by name, so the second
     * button needs its own while sharing this one handler.
     */
    private function scheduleInterviewAction(Application $application, string $name = 'scheduleInterview'): Action
    {
        return Action::make($name)
            ->label(__('applications.admin.actions.schedule_interview'))
            ->icon(Heroicon::OutlinedCalendarDays)
            ->button()
            ->schema([
                // One key per opened form, kept across resubmissions of that same
                // form, so a double click or a retry reuses the interview it
                // already booked instead of booking a second one.
                Hidden::make('schedule_request_key')
                    ->default(fn (): string => (string) Str::uuid()),
                ...$this->interviewSchedulingSchema(),
            ])
            ->modalHeading(__('applications.admin.interviews.schedule.heading'))
            ->modalDescription(__('applications.admin.interviews.schedule.description'))
            ->modalSubmitActionLabel(__('applications.admin.interviews.schedule.confirm'))
            ->action(function (array $data, ScheduleInterview $scheduleInterview): void {
                $application = $this->getApplication();
                $user = $this->getCurrentUser();

                Gate::forUser($user)->authorize('update', $application);
                $this->ensureInterviewCanBeScheduled($application, $user);

                [$scheduledAt, $endsAt, $timezone] = $this->interviewTimes($data);

                try {
                    $interview = $scheduleInterview->handle(
                        $application->company,
                        $user,
                        $application,
                        $scheduledAt,
                        $endsAt,
                        $timezone,
                        (string) $data['schedule_request_key'],
                    );
                } catch (AuthorizationException|ConnectedIntegrationReauthorizationRequired|InterviewCalendarOperationUnavailable|InterviewCalendarTerminalFailure|ConnectionException|RequestException|ModelNotFoundException|\InvalidArgumentException|LogicException $exception) {
                    $this->sendInterviewFailureNotification($exception, $application->company);
                }

                $this->refreshApplicationRecord($application);

                $notification = Notification::make()->title(__('applications.admin.interviews.notifications.scheduled'));

                if ($interview->status === InterviewStatus::Scheduled) {
                    $notification->success();
                } else {
                    $notification
                        ->title(__('applications.admin.interviews.notifications.pending'))
                        ->warning();
                }

                $notification->send();
            });
    }

    protected function rescheduleInterviewAction(): Action
    {
        return Action::make('rescheduleInterview')
            ->label(__('applications.admin.actions.reschedule_interview'))
            ->icon(Heroicon::OutlinedCalendarDays)
            ->schema($this->interviewSchedulingSchema())
            ->fillForm(function (array $arguments): array {
                $interview = $this->resolveInterview($arguments);

                return [
                    'scheduled_at' => $interview->scheduled_at->setTimezone($interview->timezone)->format('Y-m-d H:i'),
                    'duration_minutes' => (int) $interview->scheduled_at->diffInMinutes($interview->ends_at),
                    'timezone' => $interview->timezone,
                ];
            })
            ->modalHeading(__('applications.admin.interviews.reschedule.heading'))
            ->modalDescription(__('applications.admin.interviews.reschedule.description'))
            ->modalSubmitActionLabel(__('applications.admin.interviews.reschedule.confirm'))
            ->action(function (array $data, array $arguments, RescheduleInterview $rescheduleInterview): void {
                $application = $this->getApplication();
                $user = $this->getCurrentUser();
                $interview = $this->resolveInterview($arguments);

                Gate::forUser($user)->authorize('update', $application);
                $this->ensureCalendarConnected($application->company, $user);

                [$scheduledAt, $endsAt, $timezone] = $this->interviewTimes($data);

                try {
                    $rescheduleInterview->handle($application->company, $user, $interview, $scheduledAt, $endsAt, $timezone);
                } catch (AuthorizationException|ConnectedIntegrationReauthorizationRequired|InterviewCalendarOperationUnavailable|InterviewCalendarTerminalFailure|ConnectionException|RequestException|ModelNotFoundException|\InvalidArgumentException|LogicException $exception) {
                    $this->sendInterviewFailureNotification($exception, $application->company);
                }

                $this->refreshApplicationRecord($application);

                Notification::make()
                    ->title(__('applications.admin.interviews.notifications.rescheduled'))
                    ->success()
                    ->send();
            });
    }

    protected function cancelInterviewAction(): Action
    {
        return Action::make('cancelInterview')
            ->label(__('applications.admin.actions.cancel_interview'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->schema([
                Textarea::make('reason')
                    ->label(__('applications.admin.interviews.cancel.reason'))
                    ->maxLength(1000),
            ])
            ->modalHeading(__('applications.admin.interviews.cancel.heading'))
            ->modalDescription(__('applications.admin.interviews.cancel.description'))
            ->modalSubmitActionLabel(__('applications.admin.interviews.cancel.confirm'))
            ->action(function (array $data, array $arguments, CancelInterview $cancelInterview): void {
                $application = $this->getApplication();
                $user = $this->getCurrentUser();
                $interview = $this->resolveInterview($arguments);

                Gate::forUser($user)->authorize('update', $application);
                $this->ensureCalendarConnected($application->company, $user);

                try {
                    $cancelInterview->handle($application->company, $user, $interview, $data['reason'] ?? null);
                } catch (AuthorizationException|ConnectedIntegrationReauthorizationRequired|InterviewCalendarOperationUnavailable|InterviewCalendarTerminalFailure|ConnectionException|RequestException|ModelNotFoundException|\InvalidArgumentException|LogicException $exception) {
                    $this->sendInterviewFailureNotification($exception, $application->company);
                }

                $this->refreshApplicationRecord($application);

                Notification::make()
                    ->title(__('applications.admin.interviews.notifications.cancelled'))
                    ->success()
                    ->send();
            });
    }

    protected function refreshInterviewAction(): Action
    {
        return Action::make('refreshInterview')
            ->label(__('applications.admin.actions.refresh_interview'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->action(function (array $arguments, SyncInterviewResponse $syncInterviewResponse): void {
                $application = $this->getApplication();
                $user = $this->getCurrentUser();
                $interview = $this->resolveInterview($arguments);

                Gate::forUser($user)->authorize('update', $application);
                $this->ensureCalendarConnected($application->company, $user);

                try {
                    $syncInterviewResponse->handle($application->company, $user, $interview);
                } catch (AuthorizationException|ConnectedIntegrationReauthorizationRequired|InterviewCalendarOperationUnavailable|InterviewCalendarTerminalFailure|ConnectionException|RequestException|ModelNotFoundException|\InvalidArgumentException|LogicException $exception) {
                    $this->sendInterviewFailureNotification($exception, $application->company);
                }

                $this->refreshApplicationRecord($application);

                Notification::make()
                    ->title(__('applications.admin.interviews.notifications.refreshed'))
                    ->success()
                    ->send();
            });
    }

    /** @return array<int, Component> */
    private function interviewSchedulingSchema(): array
    {
        return [
            DateTimePicker::make('scheduled_at')
                ->label(__('applications.admin.interviews.fields.scheduled_at'))
                ->helperText($this->calendarAccountHint())
                ->native(false)
                ->seconds(false)
                // The value entered is wall-clock time in the selected timezone,
                // so "in the future" has to be judged in that same timezone.
                ->after(fn (Get $get): string => now($this->selectedTimezone($get))->format('Y-m-d H:i:s'))
                ->default(fn (): string => now($this->defaultTimezone())->addHour()->startOfHour()->format('Y-m-d H:i'))
                ->required(),
            TextInput::make('duration_minutes')
                ->label(__('applications.admin.interviews.fields.duration'))
                ->integer()
                ->minValue(15)
                ->maxValue(480)
                ->default(60)
                ->suffix(__('applications.admin.interviews.minutes_short'))
                ->required(),
            Select::make('timezone')
                ->label(__('applications.admin.interviews.fields.timezone'))
                ->helperText(__('applications.admin.interviews.fields.timezone_helper'))
                ->options($this->timezoneOptions())
                ->default(fn (): string => $this->defaultTimezone())
                ->searchable()
                ->live()
                ->required(),
        ];
    }

    /**
     * The timezone the agenda already resolved from this recruiter's browser,
     * falling back to the application's. It is only a default: the field stays
     * editable, and whatever is selected is what the date and time mean.
     */
    private function defaultTimezone(): string
    {
        $sessionTimezone = session('agenda.timezone');

        return is_string($sessionTimezone) && in_array($sessionTimezone, DateTimeZone::listIdentifiers(), true)
            ? $sessionTimezone
            : (string) config('app.timezone');
    }

    private function selectedTimezone(Get $get): string
    {
        $timezone = $get('timezone');

        return is_string($timezone) && in_array($timezone, DateTimeZone::listIdentifiers(), true)
            ? $timezone
            : $this->defaultTimezone();
    }

    /**
     * Names the Google account whose calendar will own the event, so the
     * recruiter is not left guessing which calendar the invitation comes from.
     */
    private function calendarAccountHint(): ?string
    {
        $accountEmail = ConnectedIntegration::query()
            ->whereBelongsTo($this->getApplication()->company)
            ->whereBelongsTo($this->getCurrentUser())
            ->where('plugin_key', 'google-calendar')
            ->where('status', ConnectedIntegrationStatus::Connected->value)
            ->value('account_email');

        if (! is_string($accountEmail) || blank($accountEmail)) {
            return null;
        }

        return __('applications.admin.interviews.fields.calendar_account', ['account' => $accountEmail]);
    }

    /**
     * @return array{connection: array{is_connected: bool, needs_reauthorization: bool, settings_url: string}, upcoming: list<array<string, mixed>>, past: list<array<string, mixed>>, cancelled: list<array<string, mixed>>, evidence: array{shows_application_evidence: bool, unresolved_count: int, criteria: list<array<string, mixed>>}, brief_items: array<int, array<string, string>>}
     */
    private function interviewsData(Application $application): array
    {
        $application->loadMissing('interviews', 'interviewBriefItems', 'job');
        $user = $this->getCurrentUser();
        $canUpdateApplication = Gate::forUser($user)->allows('update', $application);
        $interviews = ['upcoming' => [], 'past' => [], 'cancelled' => []];
        // Loaded once for the whole tab: the cards and the aggregated section
        // read the same submissions, so neither issues a query per interview,
        // per submission or per criterion.
        $feedback = $this->interviewFeedbackByInterview($application);
        $criteriaGeneration = (int) $application->job->criteria_generation;

        foreach ($application->interviews->sortBy('scheduled_at') as $interview) {
            $section = match (true) {
                $interview->status === InterviewStatus::Cancelled => 'cancelled',
                // This bucket is purely about time — has the slot ended? —
                // while feedback eligibility no longer cares about time at
                // all (only cancellation gates it). The two used to be the
                // same fact and could share one test; now they deliberately
                // no longer compose, so this restates the time test on its
                // own rather than reading it off `canReceiveFeedback()`.
                $interview->ends_at->isPast() => 'past',
                default => 'upcoming',
            };

            $interviews[$section][] = $this->interviewCardData(
                $interview,
                $user,
                $canUpdateApplication,
                $feedback->get((int) $interview->getKey()) ?? collect(),
                $criteriaGeneration,
            );
        }

        return [
            'connection' => $this->calendarConnectionData($application->company, $user),
            'upcoming' => $interviews['upcoming'],
            'past' => $interviews['past'],
            'cancelled' => $interviews['cancelled'],
            'evidence' => $this->interviewEvidenceData($application),
            'brief_items' => $application->interviewBriefItems
                ->map(fn (ApplicationInterviewBriefItem $item): array => [
                    'criterion' => $item->criterion,
                    'priority' => $item->priority,
                    'reason' => $item->reason,
                    'question' => $item->question,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, InterviewFeedback>  $feedback
     * @return array<string, mixed>
     */
    private function interviewCardData(
        Interview $interview,
        User $user,
        bool $canUpdateApplication,
        Collection $feedback,
        int $criteriaGeneration,
    ): array {
        $status = $this->enumValue($interview->status);
        $syncStatus = $this->enumValue($interview->calendar_sync_status);
        $isCancelled = $status === InterviewStatus::Cancelled->value;
        $isCalendarOwner = (int) $interview->calendar_user_id === (int) $user->getKey();
        $hasRecoverablePendingCalendarFailure = $status === InterviewStatus::Pending->value
            && ($interview->calendar_sync_terminal || $syncStatus === InterviewCalendarSyncStatus::Failed->value);
        $startsAt = $interview->scheduled_at->setTimezone($interview->timezone);
        $endsAt = $interview->ends_at->setTimezone($interview->timezone);

        return [
            'id' => (int) $interview->getKey(),
            'status' => $status,
            'status_label' => $syncStatus === InterviewCalendarSyncStatus::Failed->value && ! $isCancelled
                ? 'applications.admin.interviews.statuses.attention'
                : "applications.admin.interviews.statuses.{$status}",
            'status_color' => match ($status) {
                InterviewStatus::Scheduled->value => $syncStatus === InterviewCalendarSyncStatus::Synced->value ? 'success' : 'danger',
                InterviewStatus::Cancelled->value => 'gray',
                default => 'warning',
            },
            'sync_status' => $syncStatus,
            'sync_status_label' => "applications.admin.interviews.sync_statuses.{$syncStatus}",
            'sync_status_color' => match ($syncStatus) {
                InterviewCalendarSyncStatus::Synced->value => 'success',
                InterviewCalendarSyncStatus::Failed->value => 'danger',
                default => 'warning',
            },
            'sync_error' => $syncStatus === InterviewCalendarSyncStatus::Failed->value ? $interview->calendar_sync_error : null,
            'scheduled_at' => $startsAt->translatedFormat('M j, Y · H:i'),
            'ends_at' => $endsAt->translatedFormat('H:i'),
            'timezone' => $interview->timezone,
            'duration' => (int) $startsAt->diffInMinutes($endsAt),
            'rsvp_status' => $this->enumValue($interview->rsvp_status),
            'last_synced_at' => $interview->last_calendar_synced_at?->translatedFormat('M j, Y · H:i'),
            'cancelled_at' => $interview->cancelled_at?->translatedFormat('M j, Y · H:i'),
            'cancellation_reason' => $interview->cancellation_reason,
            'meeting_url' => filter_var($interview->meeting_url, FILTER_VALIDATE_URL) ? $interview->meeting_url : null,
            'can_reschedule' => $isCalendarOwner && ($status === InterviewStatus::Scheduled->value || $hasRecoverablePendingCalendarFailure),
            'can_cancel' => $isCalendarOwner && ! $isCancelled,
            'can_refresh' => $isCalendarOwner && ! $isCancelled,
            // Unlike rescheduling, cancelling and refreshing, this is not the
            // calendar owner's privilege: two people may interview the same
            // candidate together, and each of them records their own evidence.
            'can_record_feedback' => $canUpdateApplication && $interview->canReceiveFeedback(),
            'has_own_feedback' => $feedback
                ->contains(fn (InterviewFeedback $submission): bool => (int) $submission->submitted_by_id === (int) $user->getKey()),
            // Every submission this interview received, each attributed to its
            // author. They are listed side by side and never merged: two
            // interviewers reaching different observations both stand.
            'feedback' => $this->interviewFeedbackCardData($interview, $feedback, $criteriaGeneration),
        ];
    }

    private function activeInterviewCount(Application $application): int
    {
        $application->loadMissing('interviews');
        $now = CarbonImmutable::now();

        return $application->interviews
            ->filter(fn (Interview $interview): bool => $interview->status !== InterviewStatus::Cancelled && $interview->ends_at->isAfter($now))
            ->count();
    }

    /** @return array<string, string> */
    private function timezoneOptions(): array
    {
        return collect(DateTimeZone::listIdentifiers())
            ->mapWithKeys(fn (string $timezone): array => [$timezone => $timezone])
            ->all();
    }

    /** @param array<string, mixed> $data
     * @return array{CarbonImmutable, CarbonImmutable, string}
     */
    private function interviewTimes(array $data): array
    {
        $timezone = (string) $data['timezone'];
        $scheduledValue = $data['scheduled_at'];
        $scheduledAt = $scheduledValue instanceof DateTimeInterface
            ? CarbonImmutable::instance($scheduledValue)->setTimezone($timezone)
            : CarbonImmutable::parse((string) $scheduledValue, $timezone);
        $endsAt = $scheduledAt->addMinutes((int) $data['duration_minutes']);

        if (! $scheduledAt->isFuture()) {
            Notification::make()->title(__('applications.admin.interviews.notifications.start_must_be_future'))->danger()->send();

            throw new Halt;
        }

        return [$scheduledAt, $endsAt, $timezone];
    }

    private function ensureInterviewCanBeScheduled(Application $application, User $user): void
    {
        if (filter_var($application->candidate->email, FILTER_VALIDATE_EMAIL) === false) {
            Notification::make()->title(__('applications.admin.interviews.notifications.candidate_email_required'))->danger()->send();

            throw new Halt;
        }

        $this->ensureCalendarConnected($application->company, $user);
    }

    private function ensureCalendarConnected(Company $company, User $user): void
    {
        $connection = $this->calendarConnectionData($company, $user);

        if ($connection['is_connected']) {
            return;
        }

        $requiresReconnection = $connection['needs_reauthorization'];

        Notification::make()
            ->title(__($requiresReconnection
                ? 'applications.admin.interviews.notifications.calendar_reconnect_required'
                : 'applications.admin.interviews.notifications.calendar_connection_required'))
            ->warning()
            ->actions([
                Action::make('openCalendarSettings')
                    ->label(__($requiresReconnection
                        ? 'applications.admin.actions.reconnect_calendar'
                        : 'applications.admin.actions.connect_calendar'))
                    ->url($connection['settings_url'])
                    ->button(),
            ])
            ->send();

        throw new Halt;
    }

    /** @return array{is_connected: bool, needs_reauthorization: bool, settings_url: string} */
    private function calendarConnectionData(Company $company, User $user): array
    {
        $status = ConnectedIntegration::query()
            ->whereBelongsTo($company)
            ->whereBelongsTo($user)
            ->where('plugin_key', 'google-calendar')
            ->value('status');

        return [
            'is_connected' => $status === ConnectedIntegrationStatus::Connected,
            'needs_reauthorization' => $status === ConnectedIntegrationStatus::ReauthorizationRequired,
            'settings_url' => CalendarSettings::getUrl(tenant: $company),
        ];
    }

    /** @param array<string, mixed> $arguments */
    private function resolveInterview(array $arguments): Interview
    {
        $interviewId = $arguments['interview'] ?? null;

        abort_unless(is_numeric($interviewId), 404);

        $application = $this->getApplication();
        $user = $this->getCurrentUser();

        Gate::forUser($user)->authorize('update', $application);

        return Interview::query()
            ->whereBelongsTo($application)
            ->whereBelongsTo($application->company)
            ->findOrFail((int) $interviewId);
    }

    private function refreshApplicationRecord(Application $application): void
    {
        $this->record = ApplicationResource::getEloquentQuery()->findOrFail((int) $application->getKey());
    }

    private function sendInterviewFailureNotification(\Throwable $exception, Company $company): never
    {
        $needsReauthorization = $exception instanceof ConnectedIntegrationReauthorizationRequired;
        $notification = Notification::make()
            ->title(__($needsReauthorization
                ? 'applications.admin.interviews.notifications.calendar_reconnect_required'
                : 'applications.admin.interviews.notifications.action_failed'))
            ->danger();

        if ($needsReauthorization) {
            $notification->actions([
                Action::make('reconnectCalendar')
                    ->label(__('applications.admin.actions.reconnect_calendar'))
                    ->url(CalendarSettings::getUrl(tenant: $company))
                    ->button(),
            ]);
        }

        $notification->send();

        throw new Halt;
    }

    private function getCurrentUser(): User
    {
        $user = Filament::auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
