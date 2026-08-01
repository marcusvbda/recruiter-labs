<?php

namespace Database\Factories;

use App\Models\CvFileType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CvFileType>
 */
class CvFileTypeFactory extends Factory
{
    protected $model = CvFileType::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'extension' => fake()->unique()->lexify('???'),
            'sort' => fake()->numberBetween(1, 100),
        ];
    }
}
