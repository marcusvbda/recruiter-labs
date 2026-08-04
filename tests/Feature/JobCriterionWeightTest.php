<?php

use App\Models\Job;
use App\Models\JobCriterion;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// `JobCriterion` has no factory, so rows are created directly.

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

it('stores multiple evaluation criteria and weights for a job', function () {
    $job = Job::factory()->create();

    JobCriterion::query()->create([
        'company_id' => $job->company_id,
        'job_id' => $job->id,
        'criterion' => 'Communication clarity',
        'weight' => 4,
        'reason' => 'Clear communication is required for the role.',
    ]);

    JobCriterion::query()->create([
        'company_id' => $job->company_id,
        'job_id' => $job->id,
        'criterion' => 'System design ability',
        'weight' => 9,
        'reason' => 'The role owns reliable system design.',
    ]);

    expect($job->jobCriteria()->count())->toBe(2)
        ->and($job->jobCriteria()->pluck('criterion')->all())->toBe([
            'Communication clarity',
            'System design ability',
        ]);
});

it('round-trips the criterion weight and reason through the JobCriterion model', function () {
    $job = Job::factory()->create();

    $jobCriterion = JobCriterion::query()->create([
        'company_id' => $job->company_id,
        'job_id' => $job->id,
        'criterion' => 'Leadership skills',
        'weight' => 7,
        'reason' => 'The position mentors other engineers.',
    ]);

    $freshJobCriterion = $jobCriterion->fresh();

    expect($freshJobCriterion->criterion)->toBe('Leadership skills')
        ->and($freshJobCriterion->weight)->toBe(7)
        ->and($freshJobCriterion->weight)->toBeInt()
        ->and($freshJobCriterion->reason)->toBe('The position mentors other engineers.')
        ->and(JobCriterion::query()->find($jobCriterion->id)->job->is($job))->toBeTrue()
        ->and(JobCriterion::query()->find($jobCriterion->id)->company->is($job->company))->toBeTrue();
});
