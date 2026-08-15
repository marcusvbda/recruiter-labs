<?php

namespace App\Actions;

use App\Enums\JobCriteriaProcessingStatus;
use App\Exceptions\PlanLimitExceededException;
use App\Models\Job;
use App\Models\JobApplicationQuestion;
use App\Services\LimitManager;
use Illuminate\Support\Facades\DB;

/**
 * Copies the reusable configuration of a job — everything a recruiter would
 * otherwise retype. Applications, candidate data, AI criteria and results,
 * clicks, referrals and analytics are never copied, and the copy always starts
 * as a draft so it cannot go live by accident.
 */
class DuplicateJob
{
    public function __construct(private readonly LimitManager $limitManager) {}

    /**
     * @throws PlanLimitExceededException when the company's plan has no room for
     *                                    another job. A duplicate is a new registered
     *                                    job and consumes an allowance slot like any
     *                                    other, so the check lives here rather than
     *                                    only in the Filament action.
     */
    public function handle(Job $job): Job
    {
        $job->loadMissing(['applicationQuestions', 'acceptedCvTypes', 'coverLetterFileTypes', 'company']);

        $this->limitManager->ensureCanCreateJob($job->company);

        return DB::transaction(function () use ($job): Job {
            $copy = Job::query()->create([
                'company_id' => $job->company_id,
                'pipeline_id' => $job->pipeline_id,
                'name' => $this->availableName($job),
                'application_locale' => $job->application_locale,
                'description' => $job->description,
                'application_limit' => $job->application_limit,
                'applications_paused' => false,
                'cover_letter_required' => $job->cover_letter_required,
                'cover_letter_type' => $job->cover_letter_type,
                'published' => false,
                'criteria_processing_status' => JobCriteriaProcessingStatus::NotStarted,
                'criteria_generation' => 0,
            ]);

            $copy->acceptedCvTypes()->sync($job->acceptedCvTypes->modelKeys());
            $copy->coverLetterFileTypes()->sync($job->coverLetterFileTypes->modelKeys());

            $job->applicationQuestions->each(function (JobApplicationQuestion $question) use ($copy): void {
                $copy->applicationQuestions()->create([
                    'company_id' => $copy->company_id,
                    'question' => $question->question,
                    'response_type' => $question->response_type,
                    'description' => $question->description,
                    'required' => $question->required,
                    'sort' => $question->sort,
                ]);
            });

            return $copy;
        });
    }

    private function availableName(Job $job): string
    {
        $base = __('jobs.duplicate.name', ['name' => $job->name]);
        $name = $base;
        $suffix = 1;

        while (Job::query()->where('company_id', $job->company_id)->where('name', $name)->exists()) {
            $suffix++;
            $name = "{$base} {$suffix}";
        }

        return mb_substr($name, 0, 255);
    }
}
