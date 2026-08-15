<?php

namespace Database\Factories;

use App\Actions\ProvisionDefaultPipeline;
use App\Enums\ApplicationLocale;
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
            // Every job runs on a pipeline. Reuse the company's default one,
            // provisioning it if this company was created without model events.
            'pipeline_id' => fn (array $attributes) => app(ProvisionDefaultPipeline::class)
                ->handle(Company::query()->whereKey($attributes['company_id'])->firstOrFail())
                ->getKey(),
            'name' => $this->faker->jobTitle(),
            'application_locale' => ApplicationLocale::English,
            'applications_paused' => false,
            'application_limit' => null,
            'cover_letter_required' => false,
            'cover_letter_type' => CoverLetterType::Text,
        ];
    }
}
