<?php

namespace Database\Factories;

use App\Enums\ConnectedIntegrationStatus;
use App\Models\Company;
use App\Models\ConnectedIntegration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ConnectedIntegration> */
class ConnectedIntegrationFactory extends Factory
{
    protected $model = ConnectedIntegration::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
            'plugin_key' => 'google-calendar',
            'status' => ConnectedIntegrationStatus::Connected,
            'external_account_id' => fake()->uuid(),
            'account_email' => fake()->safeEmail(),
            'account_name' => fake()->name(),
            'access_token' => fake()->sha256(),
            'refresh_token' => fake()->sha256(),
            'granted_scopes' => ['openid', 'email'],
            'metadata' => [],
            'expires_at' => now()->addHour(),
            'connected_at' => now(),
        ];
    }
}
