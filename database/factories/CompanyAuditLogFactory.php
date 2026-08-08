<?php

namespace Database\Factories;

use App\Enums\AiConfigurationEventType;
use App\Models\Company;
use App\Models\CompanyAuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyAuditLog>
 */
class CompanyAuditLogFactory extends Factory
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
            'event' => AiConfigurationEventType::ProviderChanged->value,
            'metadata' => null,
        ];
    }
}
