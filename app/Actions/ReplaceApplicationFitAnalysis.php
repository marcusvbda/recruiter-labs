<?php

namespace App\Actions;

use App\Enums\ApplicationAnalysisStatus;
use App\Models\Application;
use App\Models\JobCriterion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ReplaceApplicationFitAnalysis
{
    private const FALLBACK_WEIGHT = 5;

    /**
     * @param  array<int, mixed>  $scores
     */
    public function handle(Application $application, array $scores, int $expectedGeneration): bool
    {
        $validated = Validator::make(
            ['scores' => $scores],
            [
                'scores' => ['required', 'array', 'min:1', 'max:20'],
                'scores.*' => ['required', 'array:criterion,score,reason,confidence'],
                'scores.*.criterion' => ['required', 'string', 'max:220'],
                'scores.*.score' => ['required', 'integer', 'between:0,100'],
                'scores.*.reason' => ['required', 'string'],
                'scores.*.confidence' => ['required', 'string', 'in:high,medium,low'],
            ],
        )->validate();

        return DB::transaction(function () use ($application, $validated, $expectedGeneration): bool {
            $lockedApplication = Application::query()->whereKey($application->getKey())->lockForUpdate()->first();

            if ($lockedApplication === null || $lockedApplication->analysis_generation !== $expectedGeneration) {
                return false;
            }

            $lockedApplication->load('job.jobCriteria');

            $weightsByCriterion = $lockedApplication->job->jobCriteria
                ->mapWithKeys(fn (JobCriterion $criterion): array => [
                    Str::lower(trim($criterion->criterion)) => $criterion->weight,
                ]);

            $rows = array_map(function (array $result) use ($lockedApplication, $weightsByCriterion): array {
                $weight = $weightsByCriterion->get(Str::lower(trim($result['criterion'])), self::FALLBACK_WEIGHT);

                return [
                    'company_id' => $lockedApplication->company_id,
                    'criterion' => $result['criterion'],
                    'weight' => $weight,
                    'score' => $result['score'],
                    'reason' => $result['reason'],
                    'confidence' => $result['confidence'],
                ];
            }, $validated['scores']);

            $lockedApplication->criterionScores()->delete();
            $lockedApplication->criterionScores()->createMany($rows);

            $weightedSum = array_sum(array_map(fn (array $row): int => $row['score'] * $row['weight'], $rows));
            $totalWeight = array_sum(array_map(fn (array $row): int => $row['weight'], $rows));

            $lockedApplication->forceFill([
                'analysis_status' => ApplicationAnalysisStatus::Completed,
                'analysis_score' => $totalWeight > 0 ? round($weightedSum / $totalWeight, 2) : null,
                'analyzed_at' => now(),
            ])->saveQuietly();

            return true;
        });
    }
}
