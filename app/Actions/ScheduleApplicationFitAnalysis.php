<?php

namespace App\Actions;

use App\Enums\ApplicationAnalysisStatus;
use App\Enums\JobCriteriaProcessingStatus;
use App\Jobs\AnalyzeApplicationFit;
use App\Models\Application;
use Illuminate\Support\Facades\DB;

class ScheduleApplicationFitAnalysis
{
    public function handle(Application $application, ?int $userId = null): void
    {
        $generation = DB::transaction(function () use ($application): ?int {
            $lockedApplication = Application::query()
                ->whereKey($application->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $job = $lockedApplication->job()->with('jobCriteria')->firstOrFail();

            if ($job->criteria_processing_status !== JobCriteriaProcessingStatus::Completed || $job->jobCriteria->isEmpty()) {
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
