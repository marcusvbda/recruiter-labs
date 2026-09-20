<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailTemplate>
 */
class EmailTemplateFactory extends Factory
{
    protected $model = EmailTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => ucfirst($this->faker->unique()->word()).' '.ucfirst($this->faker->unique()->word()).' Template',
            'subject' => 'About your application',
            'body' => '<p>Hi {{ candidate.name }}, thanks for applying.</p>',
            'is_available' => true,
        ];
    }

    public function unavailable(): self
    {
        return $this->state(['is_available' => false]);
    }
}
