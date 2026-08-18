<?php

namespace App\Actions;

use App\Data\GoogleCalendarInterviewEventData;
use App\Data\InterviewEmailContext;
use App\Enums\EmailNotificationType;
use App\Enums\InterviewCalendarSyncStatus;
use App\Enums\InterviewRsvpStatus;
use App\Enums\InterviewStatus;
use App\Exceptions\InterviewCalendarOperationUnavailable;
use App\Exceptions\InterviewCalendarTerminalFailure;
use App\Jobs\SyncInterviewResponseJob;
use App\Models\Company;
use App\Models\Interview;
use App\Models\User;
use App\Services\GoogleCalendarInterviewEventClient;
use App\Services\RecruitmentEmailDispatcher;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class RescheduleInterview
{
    private const int OperationLockSeconds = 240;

    public function __construct(
        private GoogleCalendarInterviewEventClient $calendar,
        private RecruitmentEmailDispatcher $emails,
    ) {}

    public function handle(
        Company $company,
        User $user,
        Interview $interview,
        CarbonImmutable $scheduledAt,
        CarbonImmutable $endsAt,
        string $timezone,
    ): Interview {
        Gate::forUser($user)->authorize('update', $company);
        $this->validateTimes($scheduledAt, $endsAt, $timezone);

        try {
            return Cache::lock($this->lockKey($interview), self::OperationLockSeconds)->block(5, function () use ($company, $user, $interview, $scheduledAt, $endsAt, $timezone): Interview {
                $interview = $this->ownedScheduledInterview($company, $user, $interview);
                $requiresReplacement = $interview->calendar_sync_terminal
                    || $interview->calendar_sync_status === InterviewCalendarSyncStatus::Failed;

                if ($interview->scheduled_at->equalTo($scheduledAt)
                    && $interview->ends_at->equalTo($endsAt)
                    && $interview->timezone === $timezone
                    && ! $requiresReplacement) {
                    return $interview;
                }

                $previous = $this->calendarSnapshot($interview);
                $interview->forceFill([
                    'scheduled_at' => $scheduledAt,
                    'ends_at' => $endsAt,
                    'timezone' => $timezone,
                ]);

                $createdAfterMissingEvent = false;

                if ($requiresReplacement) {
                    try {
                        $interview = $this->startReplacement($interview);
                        $event = $this->calendar->create($company, $user, $interview);
                        $createdAfterMissingEvent = true;
                    } catch (\Throwable $exception) {
                        $this->markCalendarSyncFailed($interview, $exception);

                        throw $exception;
                    }
                } else {
                    try {
                        $event = $this->calendar->update($company, $user, $interview);
                    } catch (RequestException $exception) {
                        if (! in_array($exception->response->status(), [404, 410], true)) {
                            $this->markCalendarSyncFailed($interview, $exception);

                            throw $exception;
                        }

                        try {
                            $interview = $this->startReplacement($interview);
                            $event = $this->calendar->create($company, $user, $interview);
                            $createdAfterMissingEvent = true;
                        } catch (\Throwable $createException) {
                            $this->markCalendarSyncFailed($interview, $createException);

                            throw $createException;
                        }
                    } catch (\Throwable $exception) {
                        $this->markCalendarSyncFailed($interview, $exception);

                        throw $exception;
                    }
                }

                if ($event->isCancelled) {
                    $exception = new InterviewCalendarTerminalFailure('Google Calendar returned a cancelled interview event.');
                    $this->handleReplacementTerminalFailure($company, $user, $interview, $previous, $exception, $createdAfterMissingEvent);

                    throw $exception;
                }

                if ($event->conferenceCreationFailed) {
                    $exception = new InterviewCalendarTerminalFailure('Google Calendar failed to create the interview conference.');
                    $this->handleReplacementTerminalFailure($company, $user, $interview, $previous, $exception, $createdAfterMissingEvent);

                    throw $exception;
                }

                try {
                    $interview = $this->persistReschedule($interview, $event, $createdAfterMissingEvent);
                } catch (\Throwable $exception) {
                    $replacementCleanupRestored = false;

                    try {
                        if ($createdAfterMissingEvent) {
                            $this->calendar->delete($company, $user, $event->eventId);
                            $interview = $this->restoreAfterReplacementCleanup($interview, $previous, $exception);
                            $replacementCleanupRestored = true;
                        } else {
                            $interview->forceFill($previous);
                            $this->calendar->update($company, $user, $interview);
                        }
                    } catch (\Throwable) {
                        // The persisted sync failure gives the scheduled job a recoverable reconciliation target.
                    }

                    if (! $replacementCleanupRestored) {
                        $this->markCalendarSyncFailed($interview, $exception, $exception instanceof InterviewCalendarTerminalFailure);
                    }

                    throw $exception;
                }

                if ($interview->status === InterviewStatus::Pending) {
                    SyncInterviewResponseJob::dispatch((int) $interview->getKey())
                        ->onQueue(SyncInterviewResponseJob::QUEUE)
                        ->delay(now()->addSeconds(30));

                    return $interview;
                }

                $this->emails->dispatch(
                    $company,
                    EmailNotificationType::InterviewRescheduled,
                    $this->emailContext($interview),
                );

                return $interview;
            });
        } catch (LockTimeoutException $exception) {
            throw new InterviewCalendarOperationUnavailable(
                'Another calendar operation is already in progress for this interview.',
                previous: $exception,
            );
        }
    }

    private function ownedScheduledInterview(Company $company, User $user, Interview $interview): Interview
    {
        return DB::transaction(function () use ($company, $user, $interview): Interview {
            $lockedInterview = Interview::query()
                ->withoutGlobalScopes()
                ->with(['application.candidate', 'application.job', 'company'])
                ->whereKey($interview->getKey())
                ->whereBelongsTo($company)
                ->lockForUpdate()
                ->sole();

            if ((int) $lockedInterview->calendar_user_id !== (int) $user->getKey()) {
                throw new AuthorizationException('Only the calendar owner may reschedule this interview.');
            }

            $canRecoverPendingInterview = $lockedInterview->status === InterviewStatus::Pending
                && ($lockedInterview->calendar_sync_terminal
                    || $lockedInterview->calendar_sync_status === InterviewCalendarSyncStatus::Failed);

            if ($lockedInterview->status !== InterviewStatus::Scheduled && ! $canRecoverPendingInterview) {
                throw new \LogicException('Only scheduled interviews can be rescheduled.');
            }

            return $lockedInterview;
        });
    }

    private function validateTimes(CarbonImmutable $scheduledAt, CarbonImmutable $endsAt, string $timezone): void
    {
        if (! $endsAt->isAfter($scheduledAt)) {
            throw new \InvalidArgumentException('The interview end time must be after its start time.');
        }

        if (! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new \InvalidArgumentException('The interview timezone must be a valid IANA timezone identifier.');
        }
    }

    private function persistReschedule(
        Interview $interview,
        GoogleCalendarInterviewEventData $event,
        bool $createdAfterMissingEvent,
    ): Interview {
        return DB::transaction(function () use ($interview, $event, $createdAfterMissingEvent): Interview {
            $lockedInterview = Interview::query()
                ->withoutGlobalScopes()
                ->with(['application.candidate', 'application.job', 'company'])
                ->whereKey($interview->getKey())
                ->lockForUpdate()
                ->sole();

            if ($createdAfterMissingEvent && $lockedInterview->status !== InterviewStatus::Pending) {
                throw new \LogicException('The replacement interview event is no longer pending.');
            }

            if (! $createdAfterMissingEvent && $lockedInterview->status !== InterviewStatus::Scheduled) {
                throw new \LogicException('The interview was cancelled while it was being rescheduled.');
            }

            $awaitingFreshMeeting = $createdAfterMissingEvent && ! filled($event->meetingUrl);

            $lockedInterview->forceFill([
                'scheduled_at' => $interview->scheduled_at,
                'ends_at' => $interview->ends_at,
                'timezone' => $interview->timezone,
                'calendar_event_id' => $event->eventId,
                'status' => $awaitingFreshMeeting ? InterviewStatus::Pending : InterviewStatus::Scheduled,
                'calendar_conference_id' => $createdAfterMissingEvent
                    ? $event->conferenceId
                    : $event->conferenceId ?? $lockedInterview->calendar_conference_id,
                'meeting_url' => $createdAfterMissingEvent
                    ? $event->meetingUrl
                    : $event->meetingUrl ?? $lockedInterview->meeting_url,
                'rsvp_status' => $event->rsvpStatus,
                'notification_sequence' => $createdAfterMissingEvent
                    ? $lockedInterview->notification_sequence
                    : $lockedInterview->notification_sequence + 1,
                'pending_notification_type' => $awaitingFreshMeeting
                    ? EmailNotificationType::InterviewRescheduled
                    : null,
                'calendar_sync_status' => $awaitingFreshMeeting
                    ? InterviewCalendarSyncStatus::Pending
                    : InterviewCalendarSyncStatus::Synced,
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

    private function startReplacement(Interview $interview): Interview
    {
        return DB::transaction(function () use ($interview): Interview {
            $lockedInterview = Interview::query()
                ->withoutGlobalScopes()
                ->with(['application.candidate', 'application.job', 'company'])
                ->whereKey($interview->getKey())
                ->lockForUpdate()
                ->sole();

            $canRecoverPendingInterview = $lockedInterview->status === InterviewStatus::Pending
                && ($lockedInterview->calendar_sync_terminal
                    || $lockedInterview->calendar_sync_status === InterviewCalendarSyncStatus::Failed);

            if ($lockedInterview->status !== InterviewStatus::Scheduled && ! $canRecoverPendingInterview) {
                throw new \LogicException('The interview was cancelled while its calendar event was being replaced.');
            }

            $lockedInterview->forceFill([
                'scheduled_at' => $interview->scheduled_at,
                'ends_at' => $interview->ends_at,
                'timezone' => $interview->timezone,
                'calendar_event_id' => $this->calendar->newEventId(),
                'status' => InterviewStatus::Pending,
                'calendar_conference_id' => null,
                'meeting_url' => null,
                'rsvp_status' => InterviewRsvpStatus::NeedsAction,
                'notification_sequence' => $lockedInterview->notification_sequence + 1,
                'pending_notification_type' => EmailNotificationType::InterviewRescheduled,
                'calendar_sync_status' => InterviewCalendarSyncStatus::Pending,
                'calendar_sync_terminal' => false,
                'calendar_sync_error' => null,
                'last_calendar_synced_at' => now(),
            ])->save();

            return $lockedInterview;
        });
    }

    /**
     * @return array{
     *     scheduled_at: CarbonImmutable,
     *     ends_at: CarbonImmutable,
     *     timezone: string,
     *     status: InterviewStatus,
     *     calendar_event_id: string,
     *     calendar_conference_id: string|null,
     *     meeting_url: string|null,
     *     rsvp_status: InterviewRsvpStatus,
     *     pending_notification_type: EmailNotificationType|null,
     * }
     */
    private function calendarSnapshot(Interview $interview): array
    {
        return [
            'scheduled_at' => $interview->scheduled_at,
            'ends_at' => $interview->ends_at,
            'timezone' => $interview->timezone,
            'status' => $interview->status,
            'calendar_event_id' => $interview->calendar_event_id,
            'calendar_conference_id' => $interview->calendar_conference_id,
            'meeting_url' => $interview->meeting_url,
            'rsvp_status' => $interview->rsvp_status,
            'pending_notification_type' => $interview->pending_notification_type,
        ];
    }

    /**
     * @param  array{
     *     scheduled_at: CarbonImmutable,
     *     ends_at: CarbonImmutable,
     *     timezone: string,
     *     status: InterviewStatus,
     *     calendar_event_id: string,
     *     calendar_conference_id: string|null,
     *     meeting_url: string|null,
     *     rsvp_status: InterviewRsvpStatus,
     *     pending_notification_type: EmailNotificationType|null,
     * }  $previous
     */
    private function handleReplacementTerminalFailure(
        Company $company,
        User $user,
        Interview $interview,
        array $previous,
        InterviewCalendarTerminalFailure $exception,
        bool $createdAfterMissingEvent,
    ): void {
        if (! $createdAfterMissingEvent) {
            $this->markCalendarSyncFailed($interview, $exception, true);

            return;
        }

        try {
            $this->calendar->delete($company, $user, $interview->calendar_event_id);
            $this->restoreAfterReplacementCleanup($interview, $previous, $exception);
        } catch (\Throwable $cleanupException) {
            $this->markCalendarSyncFailed(
                $interview,
                new InterviewCalendarTerminalFailure(
                    'The replacement Google Calendar event could not be cleaned up and requires manual recovery.',
                    previous: $cleanupException,
                ),
                true,
            );
        }
    }

    /**
     * @param  array{
     *     scheduled_at: CarbonImmutable,
     *     ends_at: CarbonImmutable,
     *     timezone: string,
     *     status: InterviewStatus,
     *     calendar_event_id: string,
     *     calendar_conference_id: string|null,
     *     meeting_url: string|null,
     *     rsvp_status: InterviewRsvpStatus,
     *     pending_notification_type: EmailNotificationType|null,
     * }  $previous
     */
    private function restoreAfterReplacementCleanup(
        Interview $interview,
        array $previous,
        \Throwable $exception,
    ): Interview {
        return DB::transaction(function () use ($interview, $previous, $exception): Interview {
            $lockedInterview = Interview::query()
                ->withoutGlobalScopes()
                ->with(['application.candidate', 'application.job', 'company'])
                ->whereKey($interview->getKey())
                ->lockForUpdate()
                ->sole();

            $lockedInterview->forceFill([
                'scheduled_at' => $previous['scheduled_at'],
                'ends_at' => $previous['ends_at'],
                'timezone' => $previous['timezone'],
                'status' => $previous['status'],
                'calendar_event_id' => $previous['calendar_event_id'],
                'calendar_conference_id' => $previous['calendar_conference_id'],
                'meeting_url' => $previous['meeting_url'],
                'rsvp_status' => $previous['rsvp_status'],
                'pending_notification_type' => $previous['pending_notification_type'],
                'calendar_sync_status' => InterviewCalendarSyncStatus::Failed,
                'calendar_sync_terminal' => true,
                'calendar_sync_error' => Str::limit($exception->getMessage(), 1000, ''),
                'last_calendar_synced_at' => now(),
            ])->save();

            return $lockedInterview;
        });
    }

    private function emailContext(Interview $interview): InterviewEmailContext
    {
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

    private function lockKey(Interview $interview): string
    {
        return 'interview-calendar-operation:'.$interview->getKey();
    }
}
