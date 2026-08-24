<?php

namespace App\Actions;

use App\Enums\ApplicationAnalysisStatus;
use App\Jobs\AnalyzeApplicationFit;
use App\Models\Application;
use App\Models\Job;
use Illuminate\Support\Facades\DB;

/**
 * Queues a candidate evaluation, if the job is allowed to have one and the
 * recruiting process is still open.
 *
 * Two gates, both enforced here rather than in the UI. The criteria gate is
 * {@see Job::hasConfirmedCriteria()}: a candidate is never evaluated against
 * criteria no recruiter has confirmed, and until then the application waits in
 * {@see ApplicationAnalysisStatus::AwaitingCriteria}, which
 * {@see ConfirmJobCriteria} releases. The process gate is the application's own
 * stage: a terminal outcome — hired, rejected, withdrawn, disqualified — means
 * the decision has been made, and spending AI allowance on it is waste no
 * matter which surface asked. A hidden button must not be the only thing
 * preventing that.
 */
class ScheduleApplicationFitAnalysis
{
    public function handle(Application $application, ?int $userId = null, ?int $expectedGeneration = null): void
    {
        $generation = DB::transaction(function () use ($application, $expectedGeneration): ?int {
            $lockedApplication = Application::query()
                ->whereKey($application->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($expectedGeneration !== null && $lockedApplication->analysis_generation !== $expectedGeneration) {
                return null;
            }

            // The process ended. Nothing is scheduled and nothing is rewritten:
            // the historical evaluation stays exactly as it is, and reopening the
            // candidate into an active stage — a deliberate human decision — is
            // what makes evaluation relevant again.
            if ($lockedApplication->status()->firstOrFail()->is_terminal) {
                return null;
            }

            $job = $lockedApplication->job()->with('jobCriteria')->firstOrFail();

            if (! $job->hasConfirmedCriteria() || $job->jobCriteria->isEmpty()) {
                $lockedApplication->forceFill([
                    'analysis_status' => ApplicationAnalysisStatus::AwaitingCriteria,
                ])->saveQuietly();

                return null;
            }

            $lockedApplication->forceFill([
                'analysis_status' => ApplicationAnalysisStatus::Pending,
                'analysis_generation' => $lockedApplication->analysis_generation + 1,
            ])->saveQuietly();

            return $lockedApplication->analysis_generation;
        });

        if ($generation === null) {
            return;
        }

        AnalyzeApplicationFit::dispatch($application->getKey(), $userId, $generation)
            ->onConnection((string) config('services.openai.queue_connection', 'database'))
            ->afterCommit();
    }
}
