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

it('stores multiple evaluation criteria prompts and weights for a job', function () {
    $job = Job::factory()->create();

    JobCriterion::query()->create([
        'company_id' => $job->company_id,
        'job_id' => $job->id,
        'prompt' => 'Evaluate communication clarity.',
        'weight' => 4,
    ]);

    JobCriterion::query()->create([
        'company_id' => $job->company_id,
        'job_id' => $job->id,
        'prompt' => 'Evaluate system design ability.',
        'weight' => 9,
    ]);

    expect($job->jobCriteria()->count())->toBe(2)
        ->and($job->jobCriteria()->pluck('prompt')->all())->toBe([
            'Evaluate communication clarity.',
            'Evaluate system design ability.',
        ]);
});

it('round-trips the prompt and weight through the JobCriterion model', function () {
    $job = Job::factory()->create();

    $jobCriterion = JobCriterion::query()->create([
        'company_id' => $job->company_id,
        'job_id' => $job->id,
        'prompt' => 'Evaluate leadership skills.',
        'weight' => 7,
    ]);

    $freshJobCriterion = $jobCriterion->fresh();

    expect($freshJobCriterion->prompt)->toBe('Evaluate leadership skills.')
        ->and($freshJobCriterion->weight)->toBe(7)
        ->and($freshJobCriterion->weight)->toBeInt()
        ->and(JobCriterion::query()->find($jobCriterion->id)->job->is($job))->toBeTrue()
        ->and(JobCriterion::query()->find($jobCriterion->id)->company->is($job->company))->toBeTrue();
});
