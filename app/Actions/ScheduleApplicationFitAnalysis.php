<?php

namespace App\Actions;

use App\Enums\AiExecutionOrigin;
use App\Enums\ApplicationAnalysisStatus;
use App\Jobs\AnalyzeApplicationFit;
use App\Models\Application;
use App\Models\Job;
use App\Services\AiActivityService;
use Illuminate\Support\Facades\DB;

/**
 * Queues a candidate evaluation, if the job is allowed to have one and the
 * recruiting process is still open.
 *
 * Two gates, both enforced here rather than in the UI. The criteria gate is
 * {@see Job::hasConfirmedCriteria()}: a candidate is never evaluated against
 * criteria that do not currently govern the job, and until then the application
 * waits in {@see ApplicationAnalysisStatus::AwaitingCriteria}, which
 * {@see ReleaseApplicationsForCurrentCriteria} releases once a revision becomes
 * current. The process gate is the application's own
 * stage: a terminal outcome — hired, rejected, withdrawn, disqualified — means
 * the decision has been made, and spending AI allowance on it is waste no
 * matter which surface asked. A hidden button must not be the only thing
 * preventing that.
 */
class ScheduleApplicationFitAnalysis
{
    public function handle(
        Application $application,
        ?int $userId = null,
        ?int $expectedGeneration = null,
        AiExecutionOrigin $origin = AiExecutionOrigin::UserRequested,
        ?string $trigger = 'application_analysis_requested',
    ): void {
        // Whether this call actually moved the persisted analysis status. Only
        // then does the workspace indicator have something new to say.
        $statusChanged = false;

        $generation = DB::transaction(function () use ($application, $expectedGeneration, &$statusChanged): ?int {
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

                $statusChanged = true;

                return null;
            }

            $lockedApplication->forceFill([
                'analysis_status' => ApplicationAnalysisStatus::Pending,
                'analysis_generation' => $lockedApplication->analysis_generation + 1,
            ])->saveQuietly();

            $statusChanged = true;

            return $lockedApplication->analysis_generation;
        });

        // Queued and waiting work has to be as visible as running work, and
        // `saveQuietly()` above bypasses the model events that would broadcast
        // it. Deferred to after commit so the indicator never reads a status
        // that a rollback would undo.
        if ($statusChanged) {
            DB::afterCommit(fn () => AiActivityService::broadcastForApplication($application->getKey()));
        }

        if ($generation === null) {
            return;
        }

        AnalyzeApplicationFit::dispatch($application->getKey(), $userId, $generation, origin: $origin, trigger: $trigger)
            ->onConnection((string) config('services.openai.queue_connection', 'database'))
            ->afterCommit();
    }
}
