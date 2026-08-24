<?php

namespace Database\Factories;

use App\Actions\ProvisionDefaultPipeline;
use App\Enums\ApplicationLocale;
use App\Enums\CoverLetterType;
use App\Enums\JobCriteriaProcessingStatus;
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
            'hiring_target' => 1,
            'cover_letter_required' => false,
            'cover_letter_type' => CoverLetterType::Text,
        ];
    }

    /**
     * A job whose evaluation criteria a recruiter has confirmed — the only state
     * in which candidate evaluation is allowed to run.
     *
     * @param  list<array{criterion: string, weight: int, reason?: string}>  $criteria
     */
    public function withConfirmedCriteria(array $criteria = []): static
    {
        return $this
            ->state(fn (): array => [
                'criteria_processing_status' => JobCriteriaProcessingStatus::Completed,
                'criteria_generation' => 1,
                'criteria_confirmed_generation' => 1,
                'criteria_confirmed_at' => now(),
            ])
            ->afterCreating(fn (Job $job) => $this->createCriteria($job, $criteria));
    }

    /**
     * Criteria an extraction has produced but nobody has confirmed. Candidate
     * evaluation must not run against these.
     *
     * @param  list<array{criterion: string, weight: int, reason?: string}>  $criteria
     */
    public function withCriteriaAwaitingReview(array $criteria = []): static
    {
        return $this
            ->state(fn (): array => [
                'criteria_processing_status' => JobCriteriaProcessingStatus::AwaitingReview,
                'criteria_generation' => 1,
                'criteria_confirmed_generation' => null,
                'criteria_confirmed_at' => null,
            ])
            ->afterCreating(fn (Job $job) => $this->createCriteria($job, $criteria));
    }

    /** @param  list<array{criterion: string, weight: int, reason?: string}>  $criteria */
    private function createCriteria(Job $job, array $criteria): void
    {
        $criteria = $criteria !== [] ? $criteria : [
            ['criterion' => 'Production Laravel experience', 'weight' => 10],
            ['criterion' => 'Led a team of 5+ engineers', 'weight' => 6],
        ];

        $job->jobCriteria()->createMany(array_map(fn (array $criterion): array => [
            'company_id' => $job->company_id,
            'criterion' => $criterion['criterion'],
            'weight' => $criterion['weight'],
            'reason' => $criterion['reason'] ?? 'Derived from the job description.',
        ], $criteria));
    }
}
