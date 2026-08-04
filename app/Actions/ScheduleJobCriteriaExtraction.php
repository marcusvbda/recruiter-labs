<?php

namespace App\Actions;

use App\Enums\JobCriteriaProcessingStatus;
use App\Jobs\AnalyzeJobCriteria;
use App\Models\Job;
use Illuminate\Support\Facades\DB;

class ScheduleJobCriteriaExtraction
{
    public function handle(Job $job, ?int $userId = null): void
    {
        $generation = DB::transaction(function () use ($job): int {
            $lockedJob = Job::query()->whereKey($job->getKey())->lockForUpdate()->firstOrFail();

            $lockedJob->forceFill([
                'criteria_processing_status' => JobCriteriaProcessingStatus::Pending,
                'criteria_generation' => $lockedJob->criteria_generation + 1,
            ])->saveQuietly();

            return $lockedJob->criteria_generation;
        });

        AnalyzeJobCriteria::dispatch($job->getKey(), $userId, $generation)
            ->onConnection((string) config('services.openai.queue_connection', 'database'))
            ->onQueue((string) config('services.openai.queue', 'ai'))
            ->afterCommit();
    }
}
