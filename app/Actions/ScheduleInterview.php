<?php

namespace App\Actions;

use App\Data\GoogleCalendarInterviewEventData;
use App\Data\InterviewEmailContext;
use App\Enums\ConnectedIntegrationStatus;
use App\Enums\EmailNotificationType;
use App\Enums\InterviewCalendarSyncStatus;
use App\Enums\InterviewStatus;
use App\Exceptions\InterviewCalendarOperationUnavailable;
use App\Exceptions\InterviewCalendarTerminalFailure;
use App\Jobs\SyncInterviewResponseJob;
use App\Models\Application;
use App\Models\Company;
use App\Models\ConnectedIntegration;
use App\Models\Interview;
use App\Models\User;
use App\Services\GoogleCalendarInterviewEventClient;
use App\Services\RecruitmentEmailDispatcher;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ScheduleInterview
{
    private const int OperationLockSeconds = 240;

    public function __construct(
        private GoogleCalendarInterviewEventClient $calendar,
        private RecruitmentEmailDispatcher $emails,
    ) {}

    /**
     * @param  string  $requestKey  Identifies one scheduling request. Replaying it
     *                              (double submit, retry after a timeout) returns
     *                              the interview it already produced; a new key is
     *                              what books an additional interview.
     */
    public function handle(
        Company $company,
        User $user,
        Application $application,
        CarbonImmutable $scheduledAt,
        CarbonImmutable $endsAt,
        string $timezone,
        string $requestKey,
    ): Interview {
        Gate::forUser($user)->authorize('update', $company);
        $this->validateTimes($scheduledAt, $endsAt, $timezone);
        $this->validateRequestKey($requestKey);

        $existing = $this->interviewForRequest($company, $requestKey);

        if ($existing instanceof Interview) {
            return $existing;
        }

        try {
            return Cache::lock($this->lockKey($application), self::OperationLockSeconds)->block(5, fn (): Interview => $this->scheduleInterview(
                $company,
                $user,
                $application,
                $scheduledAt,
                $endsAt,
                $timezone,
                $requestKey,
            ));
        } catch (LockTimeoutException $exception) {
            throw new InterviewCalendarOperationUnavailable(
                'Another interview is already being scheduled for this application.',
                previous: $exception,
            );
        }
    }

    /**
     * Serialized by {@see handle()} on the application, and keyed by the
     * scheduling request inside that lock: whichever call gets there first
     * creates the interview, everyone replaying the same request reuses it. So a
     * double-submitted request cannot produce two interviews, two Google
     * Calendar events, two Meet rooms or two "interview scheduled" emails.
     */
    private function scheduleInterview(
        Company $company,
        User $user,
        Application $application,
        CarbonImmutable $scheduledAt,
        CarbonImmutable $endsAt,
        string $timezone,
        string $requestKey,
    ): Interview {
        $alreadyScheduled = $this->interviewForRequest($company, $requestKey);

        if ($alreadyScheduled instanceof Interview) {
            return $alreadyScheduled;
        }

        $interview = DB::transaction(function () use ($company, $user, $application, $scheduledAt, $endsAt, $timezone, $requestKey): Interview {
            $lockedApplication = Application::query()
                ->withoutGlobalScopes()
                ->with(['candidate', 'job'])
                ->whereKey($application->getKey())
                ->whereBelongsTo($company)
                ->lockForUpdate()
                ->sole();

            if (filter_var($lockedApplication->candidate->email, FILTER_VALIDATE_EMAIL) === false) {
                throw new \InvalidArgumentException('An interview requires an application with a valid candidate email address.');
            }

            $integration = ConnectedIntegration::query()
                ->whereBelongsTo($company)
                ->whereBelongsTo($user)
                ->where('plugin_key', 'google-calendar')
                ->where('status', ConnectedIntegrationStatus::Connected->value)
                ->firstOrFail();

            $interview = new Interview([
                'company_id' => $company->getKey(),
                'application_id' => $lockedApplication->getKey(),
                'calendar_user_id' => $user->getKey(),
                'calendar_integration_id' => $integration->getKey(),
                'schedule_request_key' => $requestKey,
                'status' => InterviewStatus::Pending,
                'scheduled_at' => $scheduledAt,
                'ends_at' => $endsAt,
                'timezone' => $timezone,
                'calendar_event_id' => $this->calendar->newEventId(),
            ]);
            $interview->save();

            return $interview->load('application.candidate', 'application.job');
        });

        try {
            $event = $this->calendar->create($company, $user, $interview);
        } catch (\Throwable $exception) {
            $this->markCalendarSyncFailed($interview, $exception, $exception instanceof InterviewCalendarTerminalFailure);

            throw $exception;
        }

        try {
            $interview = $this->persistCalendarEvent($interview, $event);
        } catch (\Throwable $exception) {
            try {
                $this->calendar->delete($company, $user, $event->eventId);
            } catch (\Throwable) {
                // The next manual or scheduled sync can recover the deterministic event ID.
            }

            $this->markCalendarSyncFailed($interview, $exception, $exception instanceof InterviewCalendarTerminalFailure);

            throw $exception;
        }

        if ($interview->status === InterviewStatus::Pending) {
            SyncInterviewResponseJob::dispatch((int) $interview->getKey())
                ->onQueue(SyncInterviewResponseJob::QUEUE)
                ->delay(now()->addSeconds(30));

            return $interview;
        }

        $this->emails->dispatch($company, EmailNotificationType::InterviewScheduled, $this->emailContext($interview));

        return $interview;
    }

    private function lockKey(Application $application): string
    {
        return 'interview-schedule-application:'.$application->getKey();
    }

    /**
     * The interview a previous run of this exact scheduling request produced, if
     * any. Its calendar event, Meet room and notification already exist, so it is
     * returned untouched rather than re-created.
     */
    private function interviewForRequest(Company $company, string $requestKey): ?Interview
    {
        return Interview::query()
            ->withoutGlobalScopes()
            ->with(['application.candidate', 'application.job'])
            ->whereBelongsTo($company)
            ->where('schedule_request_key', $requestKey)
            ->first();
    }

    private function validateRequestKey(string $requestKey): void
    {
        if (! Str::isUuid($requestKey)) {
            throw new \InvalidArgumentException('A scheduling request key must be a UUID.');
        }
    }

    private function validateTimes(CarbonImmutable $scheduledAt, CarbonImmutable $endsAt, string $timezone): void
    {
        if (! $endsAt->isAfter($scheduledAt)) {
            throw new \InvalidArgumentException('The interview end time must be after its start time.');
        }

        if (! $scheduledAt->isFuture()) {
            throw new \InvalidArgumentException('An interview must start in the future.');
        }

        if (! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new \InvalidArgumentException('The interview timezone must be a valid IANA timezone identifier.');
        }
    }

    private function persistCalendarEvent(Interview $interview, GoogleCalendarInterviewEventData $event): Interview
    {
        return DB::transaction(function () use ($interview, $event): Interview {
            $lockedInterview = Interview::query()
                ->withoutGlobalScopes()
                ->with(['application.candidate', 'application.job'])
                ->whereKey($interview->getKey())
                ->lockForUpdate()
                ->sole();

            if ($lockedInterview->status === InterviewStatus::Cancelled) {
                throw new \LogicException('The interview was cancelled while its calendar event was being created.');
            }

            if ($event->isCancelled) {
                throw new InterviewCalendarTerminalFailure('Google Calendar returned a cancelled interview event.');
            }

            if ($event->conferenceCreationFailed) {
                throw new InterviewCalendarTerminalFailure('Google Calendar failed to create the interview conference.');
            }

            $hasMeetingUrl = filled($event->meetingUrl);

            $lockedInterview->forceFill([
                'status' => $hasMeetingUrl ? InterviewStatus::Scheduled : InterviewStatus::Pending,
                'calendar_event_id' => $event->eventId,
                'calendar_conference_id' => $event->conferenceId,
                'meeting_url' => $event->meetingUrl,
                'rsvp_status' => $event->rsvpStatus,
                'notification_sequence' => $hasMeetingUrl
                    ? $lockedInterview->notification_sequence + 1
                    : $lockedInterview->notification_sequence,
                'pending_notification_type' => $hasMeetingUrl
                    ? null
                    : EmailNotificationType::InterviewScheduled,
                'calendar_sync_status' => $hasMeetingUrl
                    ? InterviewCalendarSyncStatus::Synced
                    : InterviewCalendarSyncStatus::Pending,
                'calendar_sync_terminal' => false,
                'calendar_sync_error' => null,
                'last_calendar_synced_at' => now(),
            ])->save();

            return $lockedInterview;
        });
    }

    private function markCalendarSyncFailed(Interview $interview, \Throwable $exception, bool $terminal = false): void
    {
        Interview::query()
            ->withoutGlobalScopes()
            ->whereKey($interview->getKey())
            ->update([
                'calendar_sync_status' => InterviewCalendarSyncStatus::Failed->value,
                'calendar_sync_terminal' => $terminal,
                'calendar_sync_error' => Str::limit($exception->getMessage(), 1000, ''),
            ]);
    }

    private function emailContext(Interview $interview): InterviewEmailContext
    {
        $interview->loadMissing('application.candidate', 'application.job', 'company');

        return new InterviewEmailContext(
            interviewId: (int) $interview->getKey(),
            notificationSequence: $interview->notification_sequence,
            candidateName: $interview->application->candidate->name,
            candidateEmail: $interview->application->candidate->email,
            jobTitle: $interview->application->job->name,
            employerName: $interview->company->name,
            scheduledAt: $interview->scheduled_at,
            timezone: $interview->timezone,
            meetingUrl: $interview->meeting_url,
        );
    }
}
