<?php

namespace App\Actions;

use App\Enums\JobCriteriaProcessingStatus;
use App\Models\Job;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Stores the criteria and job review an extraction produced.
 *
 * The result is a *suggestion*: it lands in
 * {@see JobCriteriaProcessingStatus::AwaitingReview}, editable and clearly
 * AI-assisted, and no candidate evaluation runs against it. A recruiter has to
 * confirm it first — {@see ConfirmJobCriteria} — because extraction finishing is
 * not the same thing as evaluation criteria being approved.
 */
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
                // `present`, not `required`: the agent's schema allows zero alerts,
                // and "no material issues found" is a valid job review that
                // `required` would reject as a missing field.
                'review_alerts' => ['present', 'array', 'max:5'],
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
                'criteria_processing_status' => JobCriteriaProcessingStatus::AwaitingReview,
            ])->saveQuietly();

            $job->setRawAttributes($lockedJob->getAttributes(), true);

            return true;
        });

        return $replaced;
    }
}
