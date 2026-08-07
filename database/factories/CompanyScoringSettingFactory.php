<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanyScoringSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyScoringSetting>
 */
class CompanyScoringSettingFactory extends Factory
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
            'analysis_weight' => 60,
            'referral_weight' => 40,
        ];
    }
}
