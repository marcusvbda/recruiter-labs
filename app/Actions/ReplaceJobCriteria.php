<?php

namespace App\Actions;

use App\Enums\JobCriteriaProcessingStatus;
use App\Models\Job;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReplaceJobCriteria
{
    /**
     * @param  array<int, mixed>  $criteria
     */
    public function handle(Job $job, array $criteria, int $expectedGeneration): bool
    {
        $validated = Validator::make(
            ['criteria' => $criteria],
            [
                'criteria' => ['required', 'array', 'min:1', 'max:20'],
                'criteria.*' => ['required', 'array:criterion,weight,reason'],
                'criteria.*.criterion' => ['required', 'string', 'max:150'],
                'criteria.*.weight' => ['required', 'integer', 'between:0,10'],
                'criteria.*.reason' => ['required', 'string'],
            ],
        )->validate();

        return DB::transaction(function () use ($job, $validated, $expectedGeneration): bool {
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

            $lockedJob->forceFill([
                'criteria_processing_status' => JobCriteriaProcessingStatus::Completed,
            ])->saveQuietly();

            return true;
        });
    }
}
