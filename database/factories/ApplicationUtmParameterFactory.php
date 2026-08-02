<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\ApplicationUtmParameter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationUtmParameter>
 */
class ApplicationUtmParameterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'name' => fake()->randomElement(['utm_source', 'utm_medium', 'utm_campaign']),
            'value' => fake()->slug(2),
        ];
    }
}
