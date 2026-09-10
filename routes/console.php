<?php

// use Illuminate\Foundation\Inspiring;
// use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Artisan::command('inspire', function () {
//     $this->comment(Inspiring::quote());
// })->purpose('Display an inspiring quote');

$queues = [
    'default',
    'ai-application-analysis',
    'ai-criteria-extraction',
    'interview-sync',
    'recruitment-emails',
];

foreach ($queues as $queue) {
    Schedule::command(
        "queue:work database --queue={$queue} --stop-when-empty --max-time=55"
    )
        ->name("queue-worker:{$queue}")
        ->everyMinute()
        ->withoutOverlapping(2)
        ->runInBackground();
}

Schedule::command('interviews:sync-upcoming')
    ->everyFifteenMinutes()
    ->withoutOverlapping(15)
    ->runInBackground();

// A stalled import is only detectable half an hour after it went quiet, so
// checking every five minutes is already far finer than the signal.
Schedule::command('candidate-imports:detect-stalls')
    ->everyFiveMinutes()
    ->withoutOverlapping(5)
    ->runInBackground();

// Retention is measured in days but swept hourly: the pass is a no-op once
// nothing is due, and it also retries file deletions the disk refused.
Schedule::command('candidate-imports:sweep-retention')
    ->hourly()
    ->withoutOverlapping(30)
    ->runInBackground();
