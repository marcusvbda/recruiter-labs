<?php

namespace Database\Factories;

use App\Models\Pipeline;
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
            // Resolved before `company_id` on purpose: the status's tenant is
            // always the tenant of the pipeline that owns it.
            'pipeline_id' => Pipeline::factory(),
            'company_id' => fn (array $attributes) => Pipeline::query()
                ->whereKey($attributes['pipeline_id'])
                ->value('company_id'),
            'name' => $this->faker->unique()->word(),
            'color' => $this->faker->hexColor(),
            'order' => 0,
            'is_final_stage' => false,
            'is_hired' => false,
            'is_terminal' => false,
            'attention_after_days' => null,
            'sends_email' => false,
            'email_subject' => null,
            'email_body' => null,
        ];
    }
}
