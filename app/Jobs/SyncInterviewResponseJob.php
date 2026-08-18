<?php

namespace App\Jobs;

use App\Actions\SyncInterviewResponse;
use App\Exceptions\InterviewCalendarOperationUnavailable;
use App\Exceptions\InterviewCalendarTerminalFailure;
use App\Models\Interview;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class SyncInterviewResponseJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const string QUEUE = 'interview-sync';

    public int $tries = 50;

    public int $maxExceptions = 3;

    public int $timeout = 60;

    public int $uniqueFor = 120;

    /** @var list<int> */
    public array $backoff = [30, 90];

    public function __construct(
        public readonly int $interviewId,
        public readonly CarbonImmutable $retryUntilAt = new CarbonImmutable('+15 minutes'),
    ) {}

    public function handle(SyncInterviewResponse $syncInterviewResponse): void
    {
        $interview = Interview::query()
            ->withoutGlobalScopes()
            ->with(['company', 'calendarUser'])
            ->find($this->interviewId);

        if ($interview === null || $interview->company === null || $interview->calendarUser === null) {
            return;
        }

        if ($interview->calendar_sync_terminal) {
            return;
        }

        $rateLimitKey = 'google-calendar-sync-user:'.$interview->calendar_user_id;

        if (RateLimiter::tooManyAttempts($rateLimitKey, 4)) {
            $this->release(max(1, RateLimiter::availableIn($rateLimitKey)) + random_int(1, 5));

            return;
        }

        RateLimiter::hit($rateLimitKey, 60);

        try {
            Cache::lock($rateLimitKey.':operation', 90)->block(5, function () use ($interview, $syncInterviewResponse): void {
                $syncInterviewResponse->handle($interview->company, $interview->calendarUser, $interview);
            });
        } catch (InterviewCalendarTerminalFailure) {
            return;
        } catch (InterviewCalendarOperationUnavailable|LockTimeoutException) {
            $this->release(random_int(10, 20));

            return;
        }
    }

    public function uniqueId(): string
    {
        return (string) $this->interviewId;
    }

    public function retryUntil(): DateTimeInterface
    {
        return $this->retryUntilAt;
    }
}
