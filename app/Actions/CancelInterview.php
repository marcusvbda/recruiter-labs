<?php

namespace App\Actions;

use App\Data\InterviewEmailContext;
use App\Enums\EmailNotificationType;
use App\Enums\InterviewCalendarSyncStatus;
use App\Enums\InterviewStatus;
use App\Exceptions\InterviewCalendarOperationUnavailable;
use App\Models\Company;
use App\Models\Interview;
use App\Models\User;
use App\Services\GoogleCalendarInterviewEventClient;
use App\Services\RecruitmentEmailDispatcher;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CancelInterview
{
    private const int OperationLockSeconds = 240;

    public function __construct(
        private GoogleCalendarInterviewEventClient $calendar,
        private RecruitmentEmailDispatcher $emails,
    ) {}

    public function handle(Company $company, User $user, Interview $interview, ?string $reason = null): Interview
    {
        Gate::forUser($user)->authorize('update', $company);

        try {
            return Cache::lock($this->lockKey($interview), self::OperationLockSeconds)->block(5, function () use ($company, $user, $interview, $reason): Interview {
                $interview = $this->ownedInterview($company, $user, $interview);

                if ($interview->status === InterviewStatus::Cancelled) {
                    return $interview;
                }

                try {
                    $this->calendar->delete($company, $user, $interview->calendar_event_id);
                } catch (\Throwable $exception) {
                    $this->markCalendarSyncFailed($interview, $exception);

                    throw $exception;
                }

                $interview = DB::transaction(function () use ($interview, $reason): Interview {
                    $lockedInterview = Interview::query()
                        ->withoutGlobalScopes()
                        ->with(['application.candidate', 'application.job', 'company'])
                        ->whereKey($interview->getKey())
                        ->lockForUpdate()
                        ->sole();

                    if ($lockedInterview->status === InterviewStatus::Cancelled) {
                        return $lockedInterview;
                    }

                    $lockedInterview->forceFill([
                        'status' => InterviewStatus::Cancelled,
                        'cancelled_at' => now(),
                        'cancellation_reason' => $reason,
                        'notification_sequence' => $lockedInterview->notification_sequence + 1,
                        'pending_notification_type' => null,
                        'calendar_sync_status' => InterviewCalendarSyncStatus::Synced,
                        'calendar_sync_terminal' => false,
                        'calendar_sync_error' => null,
                        'last_calendar_synced_at' => now(),
                    ])->save();

                    return $lockedInterview;
                });

                $this->emails->dispatch(
                    $company,
                    EmailNotificationType::InterviewCancelled,
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

    private function ownedInterview(Company $company, User $user, Interview $interview): Interview
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
                throw new AuthorizationException('Only the calendar owner may cancel this interview.');
            }

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

    private function markCalendarSyncFailed(Interview $interview, \Throwable $exception): void
    {
        Interview::query()
            ->withoutGlobalScopes()
            ->whereKey($interview->getKey())
            ->update([
                'calendar_sync_status' => InterviewCalendarSyncStatus::Failed->value,
                'calendar_sync_error' => Str::limit($exception->getMessage(), 1000, ''),
            ]);
    }

    private function lockKey(Interview $interview): string
    {
        return 'interview-calendar-operation:'.$interview->getKey();
    }
}
