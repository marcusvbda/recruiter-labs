<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Job;
use App\Models\JobClick;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobClick>
 */
class JobClickFactory extends Factory
{
    protected $model = JobClick::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'job_id' => fn (array $attributes) => Job::factory()->create([
                'company_id' => $attributes['company_id'],
            ])->id,
            'referral_id' => null,
            'ip_address' => $this->faker->ipv4(),
        ];
    }
}
