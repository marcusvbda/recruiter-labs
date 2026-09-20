<?php

namespace App\Actions;

use App\Enums\AiExecutionOrigin;
use App\Enums\ApplicationAnalysisStatus;
use App\Models\Application;
use App\Models\Job;

/**
 * Releases the candidate evaluations that were waiting for the job's current
 * evaluation criteria.
 *
 * Two populations qualify: applications still waiting because no criteria
 * governed the job yet, and applications whose stored evaluation measured a
 * superseded revision. Applications whose process already ended keep their
 * historical evaluation — re-running an evaluation for somebody who was
 * rejected months ago spends AI allowance on a decision nobody is going to make
 * again.
 *
 * Everything goes through {@see ScheduleApplicationFitAnalysis}, so quota,
 * queue, terminal-stage and locking behaviour are unchanged.
 */
class ReleaseApplicationsForCurrentCriteria
{
    public function __construct(
        private readonly ScheduleApplicationFitAnalysis $scheduleApplicationFitAnalysis,
    ) {}

    public function handle(Job $job, ?int $userId = null, string $trigger = 'criteria_activated'): void
    {
        $job->applications()
            ->inProcess()
            ->where(fn ($query) => $query
                ->where('analysis_status', ApplicationAnalysisStatus::AwaitingCriteria)
                ->orWhere(fn ($stale) => $stale
                    ->where('analysis_status', ApplicationAnalysisStatus::Completed)
                    ->where(fn ($revision) => $revision
                        ->whereNull('analysis_criteria_generation')
                        ->orWhere('analysis_criteria_generation', '!=', $job->criteria_generation))))
            ->get()
            ->each(function (Application $application) use ($userId, $trigger): void {
                $this->scheduleApplicationFitAnalysis->handle(
                    $application,
                    $userId,
                    origin: AiExecutionOrigin::Automatic,
                    trigger: $trigger,
                );
            });
    }
}
