<?php

namespace Database\Factories;

use App\Enums\CoverLetterType;
use App\Models\Company;
use App\Models\Job;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Job>
 */
class JobFactory extends Factory
{
    protected $model = Job::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => $this->faker->jobTitle(),
            'cover_letter_required' => false,
            'cover_letter_type' => CoverLetterType::Text,
        ];
    }
}
