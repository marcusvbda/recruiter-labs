<?php

namespace Database\Factories;

use App\Enums\ApplicationQuestionType;
use App\Models\Company;
use App\Models\Job;
use App\Models\JobApplicationQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobApplicationQuestion>
 */
class JobApplicationQuestionFactory extends Factory
{
    protected $model = JobApplicationQuestion::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'job_id' => fn (array $attributes) => Job::factory()->create(['company_id' => $attributes['company_id']])->id,
            'question' => fake()->sentence(),
            'response_type' => fake()->randomElement(ApplicationQuestionType::cases())->value,
            'description' => fake()->optional()->sentence(),
            'required' => fake()->boolean(),
            'sort' => 0,
        ];
    }
}
