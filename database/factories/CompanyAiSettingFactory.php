<?php

namespace Database\Factories;

use App\Enums\AiCredentialStatus;
use App\Enums\AiProvider;
use App\Models\Company;
use App\Models\CompanyAiSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyAiSetting>
 */
class CompanyAiSettingFactory extends Factory
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
            'provider' => AiProvider::Platform,
            'openai_api_key' => null,
            'model' => 'gpt-4o-mini',
            'credential_status' => AiCredentialStatus::NotConfigured,
            'validated_at' => null,
        ];
    }
}
