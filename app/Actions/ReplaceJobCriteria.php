<?php

namespace App\Actions;

use App\Enums\ApplicationAnalysisStatus;
use App\Enums\JobCriteriaProcessingStatus;
use App\Models\Application;
use App\Models\Job;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReplaceJobCriteria
{
    /**
     * @param  array<int, mixed>  $criteria
     * @param  array<int, mixed>  $reviewAlerts
     */
    public function handle(Job $job, array $criteria, array $reviewAlerts, int $expectedGeneration): bool
    {
        $validated = Validator::make(
            ['criteria' => $criteria, 'review_alerts' => $reviewAlerts],
            [
                'criteria' => ['required', 'array', 'min:1', 'max:20'],
                'criteria.*' => ['required', 'array:criterion,weight,reason'],
                'criteria.*.criterion' => ['required', 'string', 'max:150'],
                'criteria.*.weight' => ['required', 'integer', 'between:0,10'],
                'criteria.*.reason' => ['required', 'string', 'max:150'],
                'review_alerts' => ['required', 'array', 'max:5'],
                'review_alerts.*' => ['required', 'array:category,severity,excerpt,issue,suggestion'],
                'review_alerts.*.category' => ['required', 'string', 'max:80'],
                'review_alerts.*.severity' => ['required', 'string', 'in:high,medium,low'],
                'review_alerts.*.excerpt' => ['nullable', 'string', 'max:220'],
                'review_alerts.*.issue' => ['required', 'string', 'max:220'],
                'review_alerts.*.suggestion' => ['required', 'string', 'max:220'],
            ],
        )->validate();

        $replaced = DB::transaction(function () use ($job, $validated, $expectedGeneration): bool {
            $lockedJob = Job::query()->whereKey($job->getKey())->lockForUpdate()->first();

            if ($lockedJob === null || $lockedJob->criteria_generation !== $expectedGeneration) {
                return false;
            }

            $lockedJob->jobCriteria()->delete();
            $lockedJob->jobCriteria()->createMany(array_map(
                fn (array $criterion): array => [
                    'company_id' => $lockedJob->company_id,
                    ...$criterion,
                ],
                $validated['criteria'],
            ));

            $alertRows = [];

            foreach ($validated['review_alerts'] as $sortOrder => $alert) {
                $alertRows[] = [
                    ...$alert,
                    'company_id' => $lockedJob->company_id,
                    'sort_order' => $sortOrder,
                ];
            }

            $lockedJob->reviewAlerts()->delete();
            $lockedJob->reviewAlerts()->createMany($alertRows);

            $lockedJob->forceFill([
                'criteria_processing_status' => JobCriteriaProcessingStatus::Completed,
            ])->saveQuietly();

            return true;
        });

        if ($replaced) {
            $job->applications()
                ->where('analysis_status', ApplicationAnalysisStatus::AwaitingCriteria)
                ->get()
                ->each(function (Application $application): void {
                    app(ScheduleApplicationFitAnalysis::class)->handle($application);
                });
        }

        return $replaced;
    }
}
