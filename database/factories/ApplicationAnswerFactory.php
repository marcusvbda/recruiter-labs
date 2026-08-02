<?php

namespace Database\Factories;

use App\Enums\ApplicationQuestionType;
use App\Models\Application;
use App\Models\ApplicationAnswer;
use App\Models\Company;
use App\Models\JobApplicationQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationAnswer>
 */
class ApplicationAnswerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'application_id' => fn (array $attributes): int => Application::factory()
                ->create(['company_id' => $attributes['company_id']])
                ->id,
            'job_application_question_id' => function (array $attributes): int {
                $application = Application::query()
                    ->whereKey((int) $attributes['application_id'])
                    ->firstOrFail();

                return JobApplicationQuestion::factory()->create([
                    'company_id' => $attributes['company_id'],
                    'job_id' => $application->job_id,
                ])->id;
            },
            'question_snapshot' => fake()->sentence(),
            'response_type' => ApplicationQuestionType::Text,
            'value_text' => fake()->sentence(),
            'value_number' => null,
        ];
    }
}
