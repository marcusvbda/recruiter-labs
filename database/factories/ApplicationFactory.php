<?php

namespace Database\Factories;

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
            'status_id' => fn (array $attributes) => Status::factory()->create(['company_id' => $attributes['company_id']])->id,
        ];
    }
}
