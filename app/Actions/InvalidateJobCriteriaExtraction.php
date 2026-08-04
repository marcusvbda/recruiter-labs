<?php

namespace App\Actions;

use App\Enums\JobCriteriaProcessingStatus;
use App\Models\Job;
use Illuminate\Support\Facades\DB;

class InvalidateJobCriteriaExtraction
{
    public function handle(Job $job): void
    {
        DB::transaction(function () use ($job): void {
            $lockedJob = Job::query()->whereKey($job->getKey())->lockForUpdate()->firstOrFail();

            $lockedJob->forceFill([
                'criteria_processing_status' => JobCriteriaProcessingStatus::Completed,
                'criteria_generation' => $lockedJob->criteria_generation + 1,
            ])->saveQuietly();

            $job->setRawAttributes($lockedJob->getAttributes(), true);
        });
    }
}
