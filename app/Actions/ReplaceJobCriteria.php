<?php

namespace App\Actions;

use App\Enums\CompanyMilestone;
use App\Enums\JobCriteriaProcessingStatus;
use App\Models\Job;
use App\Services\AiActivityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Stores the criteria and job review an extraction produced, and makes them the
 * criteria that govern candidate evaluation.
 *
 * A successful extraction activates itself: the revision lands in
 * {@see JobCriteriaProcessingStatus::Completed} with the confirmation columns
 * pointing at the generation that was just written, so every existing reader of
 * `Job::hasConfirmedCriteria()` keeps working unchanged. The criteria stay
 * editable and clearly labelled as AI-generated, and a recruiter edit is
 * authoritative the moment it is saved — there is no separate approval click.
 * `criteria_confirmed_by_id` is null because no human confirmed anything: the
 * activation is the system's, and attributing it to a user would invent a
 * decision nobody made.
 *
 * Human ownership is unchanged where it matters: the AI still proposes, and
 * nothing here moves, rejects, hires or closes an application. It only releases
 * the evaluations that were waiting for criteria to exist.
 */
class ReplaceJobCriteria
{
    public function __construct(
        private readonly ReleaseApplicationsForCurrentCriteria $releaseApplicationsForCurrentCriteria,
        private readonly CaptureCompanyMilestone $captureCompanyMilestone,
    ) {}

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
                'criteria_processing_status' => JobCriteriaProcessingStatus::Completed,
                'criteria_confirmed_generation' => $lockedJob->criteria_generation,
                'criteria_confirmed_at' => now(),
                'criteria_confirmed_by_id' => null,
            ])->saveQuietly();

            $job->setRawAttributes($lockedJob->getAttributes(), true);

            return true;
        });

        if ($replaced) {
            // The activation write above is a `saveQuietly()` on purpose, so it
            // bypasses model events: the AI Activity indicator is told here.
            AiActivityService::broadcast($job->company);

            $this->captureCompanyMilestone->handle((int) $job->company_id, CompanyMilestone::FirstCriteriaConfirmed);

            $this->releaseApplicationsForCurrentCriteria->handle($job, trigger: 'criteria_activated');
        }

        return $replaced;
    }
}
