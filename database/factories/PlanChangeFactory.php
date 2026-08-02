<?php

namespace Database\Factories;

use App\Enums\PlanChangeSource;
use App\Models\Company;
use App\Models\Plan;
use App\Models\PlanChange;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanChange>
 */
class PlanChangeFactory extends Factory
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
            'previous_plan_id' => fn (): int => Plan::default()->getKey(),
            'new_plan_id' => fn (): int => Plan::query()->where('slug', 'pro')->value('id') ?? Plan::default()->getKey(),
            'changed_by_id' => User::factory(),
            'source' => PlanChangeSource::ManualSettings,
            'metadata' => null,
        ];
    }
}
