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
use App\Models\Company;
use App\Models\Interview;
use App\Models\User;
use App\Services\GoogleCalendarInterviewEventClient;
use App\Services\RecruitmentEmailDispatcher;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class SyncInterviewResponse
{
    private const int OperationLockSeconds = 240;

    public function __construct(
        private GoogleCalendarInterviewEventClient $calendar,
        private RecruitmentEmailDispatcher $emails,
    ) {}

    public function handle(Company $company, User $user, Interview $interview): Interview
    {
        Gate::forUser($user)->authorize('update', $company);

        try {
            return Cache::lock($this->lockKey($interview), self::OperationLockSeconds)->block(5, function () use ($company, $user, $interview): Interview {
                $interview = $this->ownedInterview($company, $user, $interview);

                if ($interview->status === InterviewStatus::Cancelled) {
                    return $interview;
                }

                try {
                    $event = $this->calendar->find($company, $user, $interview->calendar_event_id, $interview);
                } catch (RequestException $exception) {
                    if (in_array($exception->response->status(), [404, 410], true)) {
                        $terminalException = new InterviewCalendarTerminalFailure(
                            'The Google Calendar event no longer exists and requires manual recovery.',
                            previous: $exception,
                        );
                        $this->markCalendarSyncFailed($interview, $terminalException, true);

                        throw $terminalException;
                    }

                    $this->markCalendarSyncFailed($interview, $exception);

                    throw $exception;
                } catch (\Throwable $exception) {
                    $this->markCalendarSyncFailed($interview, $exception);

                    throw $exception;
                }

                if ($event->isCancelled) {
                    $exception = new InterviewCalendarTerminalFailure('The Google Calendar event has been cancelled remotely.');
                    $this->markCalendarSyncFailed($interview, $exception, true);

                    throw $exception;
                }

                if ($event->conferenceCreationFailed) {
                    $exception = new InterviewCalendarTerminalFailure('Google Calendar failed to create the interview conference.');
                    $this->markCalendarSyncFailed($interview, $exception, true);

                    throw $exception;
                }

                $wasPending = $interview->status === InterviewStatus::Pending;
                $notificationType = $interview->pending_notification_type ?? EmailNotificationType::InterviewScheduled;
                $interview = $this->persistSync($interview, $event, $notificationType);

                if ($wasPending && $interview->status === InterviewStatus::Scheduled) {
                    $this->emails->dispatch($company, $notificationType, $this->emailContext($interview));
                }

                return $interview;
            });
        } catch (LockTimeoutException $exception) {
            throw new InterviewCalendarOperationUnavailable(
                'Another calendar operation is already in progress for this interview.',
                previous: $exception,
            );
        }
    }

    private function ownedInterview(Company $company, User $user, Interview $interview): Interview
    {
        $interview = Interview::query()
            ->withoutGlobalScopes()
            ->with(['application.candidate', 'application.job', 'company'])
            ->whereKey($interview->getKey())
            ->whereBelongsTo($company)
            ->sole();

        if ((int) $interview->calendar_user_id !== (int) $user->getKey()) {
            throw new AuthorizationException('Only the calendar owner may sync this interview.');
        }

        return $interview;
    }

    private function persistSync(
        Interview $interview,
        GoogleCalendarInterviewEventData $event,
        EmailNotificationType $notificationType,
    ): Interview {
        return DB::transaction(function () use ($interview, $event, $notificationType): Interview {
            $lockedInterview = Interview::query()
                ->withoutGlobalScopes()
                ->with(['application.candidate', 'application.job', 'company'])
                ->whereKey($interview->getKey())
                ->lockForUpdate()
                ->sole();

            if ($lockedInterview->status === InterviewStatus::Cancelled) {
                return $lockedInterview;
            }

            $hasMeetingUrl = filled($event->meetingUrl);
            $isActivating = $lockedInterview->status === InterviewStatus::Pending && $hasMeetingUrl;
            $shouldIncrementNotificationSequence = $isActivating
                && $notificationType === EmailNotificationType::InterviewScheduled;
            $attributes = [
                'status' => $isActivating ? InterviewStatus::Scheduled : $lockedInterview->status,
                'calendar_event_id' => $event->eventId,
                'calendar_conference_id' => $event->conferenceId ?? $lockedInterview->calendar_conference_id,
                'rsvp_status' => $event->rsvpStatus,
                'notification_sequence' => $shouldIncrementNotificationSequence
                    ? $lockedInterview->notification_sequence + 1
                    : $lockedInterview->notification_sequence,
                'pending_notification_type' => $isActivating
                    ? null
                    : $lockedInterview->pending_notification_type,
                'calendar_sync_status' => $lockedInterview->status === InterviewStatus::Pending && ! $hasMeetingUrl
                    ? InterviewCalendarSyncStatus::Pending
                    : InterviewCalendarSyncStatus::Synced,
                'calendar_sync_terminal' => false,
                'calendar_sync_error' => null,
                'last_calendar_synced_at' => now(),
            ];

            if ($event->meetingUrl !== null) {
                $attributes['meeting_url'] = $event->meetingUrl;
            }

            if ($event->rsvpStatus !== InterviewRsvpStatus::NeedsAction
                && $lockedInterview->rsvp_status !== $event->rsvpStatus) {
                $attributes['rsvp_responded_at'] = now();
            }

            $lockedInterview->forceFill($attributes)->save();

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

    private function lockKey(Interview $interview): string
    {
        return 'interview-calendar-operation:'.$interview->getKey();
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
}
