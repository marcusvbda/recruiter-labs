<?php

namespace App\Actions;

use App\Models\Job;

/**
 * Starts the first criteria suggestion only for a newly persisted job with
 * substantive role context. It is deliberately creation-only: later edits
 * retain the existing review and explicit-regeneration contract.
 */
class ScheduleInitialJobCriteriaExtraction
{
    public function __construct(private readonly ScheduleJobCriteriaExtraction $scheduleJobCriteriaExtraction) {}

    public function handle(Job $job): void
    {
        $this->scheduleJobCriteriaExtraction->handleInitial($job);
    }
}
