<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Job;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Referral>
 */
class ReferralFactory extends Factory
{
    protected $model = Referral::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            // Resolved after `company_id`: a caller who forgets `->for()` still
            // gets a tenancy-consistent fixture, since both the job and the
            // referring user belong to the same company as the referral.
            'job_id' => fn (array $attributes) => Job::factory()->create(['company_id' => $attributes['company_id']])->id,
            'user_id' => function (array $attributes) {
                $user = User::factory()->create();
                $user->companies()->attach($attributes['company_id']);

                return $user->id;
            },
        ];
    }
}
