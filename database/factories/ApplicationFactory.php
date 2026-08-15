<?php

namespace Database\Factories;

use App\Enums\ApplicationAnalysisStatus;
use App\Enums\ApplicationCoverLetterType;
use App\Enums\ApplicationSource;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Job;
use App\Models\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    protected $model = Application::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            // Resolved after `company_id`: a caller who forgets `->for()` still
            // gets a tenancy-consistent fixture, since the job, candidate and
            // status all belong to the same company as the application.
            'job_id' => fn (array $attributes) => Job::factory()->create(['company_id' => $attributes['company_id']])->id,
            'candidate_id' => fn (array $attributes) => Candidate::factory()->create(['company_id' => $attributes['company_id']])->id,
            // New applications always start in the first status of the job's
            // own pipeline, exactly like a real submission would.
            'status_id' => function (array $attributes): int {
                $job = Job::query()->whereKey($attributes['job_id'])->firstOrFail();

                $status = Status::query()
                    ->where('pipeline_id', $job->pipeline_id)
                    ->orderBy('order')
                    ->orderBy('id')
                    ->first();

                return (int) ($status?->getKey() ?? Status::factory()->create([
                    'company_id' => $job->company_id,
                    'pipeline_id' => $job->pipeline_id,
                ])->getKey());
            },
            'referral_id' => null,
            'source' => ApplicationSource::Direct,
            'analysis_status' => ApplicationAnalysisStatus::Pending,
            'cover_letter_type' => ApplicationCoverLetterType::None,
            'cover_letter_text' => null,
            'submitted_ip' => $this->faker->ipv4(),
        ];
    }
}
