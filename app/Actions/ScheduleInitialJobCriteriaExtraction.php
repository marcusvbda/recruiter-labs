<?php

namespace App\Actions;

use App\Models\Job;

/**
 * Starts the first criteria suggestion only while the job still has its
 * untouched initial criteria state and substantive role context. This covers a
 * description added after a blank job was first saved without changing the
 * explicit-regeneration or human-review contract for later edits.
 */
class ScheduleInitialJobCriteriaExtraction
{
    public function __construct(private readonly ScheduleJobCriteriaExtraction $scheduleJobCriteriaExtraction) {}

    public function handle(Job $job, string $trigger = 'job_created'): bool
    {
        return $this->scheduleJobCriteriaExtraction->handleInitial($job, $trigger);
    }
}
