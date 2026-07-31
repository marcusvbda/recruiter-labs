<?php

namespace Database\Factories;

use App\Models\Candidate;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Candidate>
 */
class CandidateFactory extends Factory
{
    protected $model = Candidate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => $this->faker->name(),
            'email' => $this->faker->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'socials' => [],
        ];
    }
}
