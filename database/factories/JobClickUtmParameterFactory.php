<?php

namespace Database\Factories;

use App\Models\JobClick;
use App\Models\JobClickUtmParameter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobClickUtmParameter>
 */
class JobClickUtmParameterFactory extends Factory
{
    protected $model = JobClickUtmParameter::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_click_id' => JobClick::factory(),
            'name' => 'utm_source',
            'value' => $this->faker->randomElement(['linkedin', 'google', 'newsletter']),
        ];
    }
}
