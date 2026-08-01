<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Status>
 */
class StatusFactory extends Factory
{
    protected $model = Status::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => $this->faker->unique()->word(),
            'color' => $this->faker->hexColor(),
            'order' => 0,
            'is_hired' => false,
        ];
    }
}
