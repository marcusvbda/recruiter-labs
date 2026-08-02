<?php

namespace Database\Factories;

use App\Enums\AiProvider;
use App\Enums\AiUsageStatus;
use App\Models\AiUsageRecord;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiUsageRecord>
 */
class AiUsageRecordFactory extends Factory
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
            'user_id' => null,
            'application_id' => null,
            'operation' => fake()->randomElement(['cv_analysis', 'candidate_summary']),
            'provider' => AiProvider::Platform,
            'model' => 'gpt-4o-mini',
            'input_tokens' => fake()->numberBetween(100, 2000),
            'output_tokens' => fake()->numberBetween(50, 1000),
            'cached_tokens' => 0,
            'estimated_cost' => fake()->randomFloat(6, 0.000001, 0.1),
            'duration_ms' => fake()->numberBetween(100, 10000),
            'status' => AiUsageStatus::Completed,
            'used_own_key' => false,
        ];
    }
}
