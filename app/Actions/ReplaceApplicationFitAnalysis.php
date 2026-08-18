<?php

namespace App\Actions;

use App\Enums\ApplicationAnalysisStatus;
use App\Models\Application;
use App\Models\ApplicationInterviewBriefItem;
use App\Models\JobCriterion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReplaceApplicationFitAnalysis
{
    private const FALLBACK_WEIGHT = 5;

    /**
     * @param  array<int, mixed>  $scores
     * @param  array<int, mixed>  $interviewBriefItems
     */
    public function handle(Application $application, array $scores, array $interviewBriefItems, int $expectedGeneration): bool
    {
        $validated = Validator::make(
            ['scores' => $scores, 'interview_brief_items' => $interviewBriefItems],
            [
                'scores' => ['required', 'array', 'min:1', 'max:20'],
                'scores.*' => ['required', 'array:criterion,score,reason,confidence'],
                'scores.*.criterion' => ['required', 'string', 'max:220'],
                'scores.*.score' => ['required', 'integer', 'between:0,100'],
                'scores.*.reason' => ['required', 'string', 'max:220'],
                'scores.*.confidence' => ['required', 'string', 'in:high,medium,low'],
                'interview_brief_items' => ['required', 'array', 'max:6'],
                'interview_brief_items.*' => ['required', 'array:criterion,priority,reason,question'],
                'interview_brief_items.*.criterion' => ['required', 'string', 'max:220'],
                'interview_brief_items.*.priority' => ['required', 'string', 'in:high,medium,low'],
                'interview_brief_items.*.reason' => ['required', 'string', 'max:220'],
                'interview_brief_items.*.question' => ['required', 'string', 'max:300'],
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

            ApplicationInterviewBriefItem::query()
                ->where('application_id', $lockedApplication->getKey())
                ->delete();
            $lockedApplication->criterionScores()->delete();
            $createdScores = $lockedApplication->criterionScores()->createMany($rows);

            $scoresByCriterion = $createdScores->groupBy(
                fn ($score): string => Str::lower(trim($score->criterion)),
            );

            $briefRows = [];
            $now = now();

            foreach ($validated['interview_brief_items'] as $sortOrder => $briefItem) {
                $matchingScores = $scoresByCriterion->get(Str::lower(trim($briefItem['criterion'])));

                if ($matchingScores === null || $matchingScores->count() !== 1) {
                    throw ValidationException::withMessages([
                        'interview_brief_items' => 'Each interview brief item must reference one returned criterion score.',
                    ]);
                }

                $criterionScore = $matchingScores->first();

                $briefRows[] = [
                    'company_id' => $lockedApplication->company_id,
                    'application_id' => $lockedApplication->getKey(),
                    'application_criterion_score_id' => $criterionScore->getKey(),
                    'criterion' => $criterionScore->criterion,
                    'priority' => $briefItem['priority'],
                    'reason' => $briefItem['reason'],
                    'question' => $briefItem['question'],
                    'sort_order' => $sortOrder,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($briefRows !== []) {
                ApplicationInterviewBriefItem::query()->insert($briefRows);
            }

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
