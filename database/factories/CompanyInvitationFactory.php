<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CompanyInvitation> */
class CompanyInvitationFactory extends Factory
{
    protected $model = CompanyInvitation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'email' => fake()->safeEmail(),
            'token_hash' => CompanyInvitation::hashToken(CompanyInvitation::generateToken()),
            'invited_by_id' => User::factory(),
            'expires_at' => now()->addDays(7),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => [
            'revoked_at' => now(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (): array => [
            'accepted_at' => now(),
            'accepted_by_id' => User::factory(),
        ]);
    }
}
