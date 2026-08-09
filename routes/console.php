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
