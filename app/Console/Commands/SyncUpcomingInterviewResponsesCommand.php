<?php

namespace App\Console\Commands;

use App\Enums\InterviewStatus;
use App\Jobs\SyncInterviewResponseJob;
use App\Models\Interview;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('interviews:sync-upcoming')]
#[Description('Queue RSVP and Google Meet synchronization for upcoming interviews')]
class SyncUpcomingInterviewResponsesCommand extends Command
{
    public function handle(): int
    {
        $queuedInterviewCount = 0;

        Interview::query()
            ->withoutGlobalScopes()
            ->whereIn('status', [InterviewStatus::Pending->value, InterviewStatus::Scheduled->value])
            ->where('calendar_sync_terminal', false)
            ->whereBetween('scheduled_at', [now(), now()->addWeeks(2)])
            ->where(function ($query): void {
                $query
                    ->whereNull('last_calendar_synced_at')
                    ->orWhere('last_calendar_synced_at', '<=', now()->subMinutes(10));
            })
            ->orderBy('id')
            ->eachById(function (Interview $interview) use (&$queuedInterviewCount): void {
                SyncInterviewResponseJob::dispatch((int) $interview->getKey())
                    ->onQueue(SyncInterviewResponseJob::QUEUE);
                $queuedInterviewCount++;
            }, 100);

        $this->components->info("Queued upcoming interview synchronization for {$queuedInterviewCount} interviews.");

        return self::SUCCESS;
    }
}
