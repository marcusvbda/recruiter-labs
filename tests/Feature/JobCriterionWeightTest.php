<?php

use App\Models\Criterion;
use App\Models\Job;
use App\Models\JobCriterion;
use Database\Seeders\PlanSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Note: `JobCriterion` has no factory (it is a real Pivot model with its
// own auto-incrementing `id` and an extra `weight` column, not a plain
// attribute-array pivot), so rows are created directly via
// `JobCriterion::query()->create()` rather than `$job->criteria()->attach()`
// — `attach()` would not populate the required `company_id` column.

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

it('attaches multiple criteria to a job with a weight each', function () {
    $job = Job::factory()->create();
    $criterionA = Criterion::factory()->for($job->company)->create();
    $criterionB = Criterion::factory()->for($job->company)->create();

    JobCriterion::query()->create([
        'company_id' => $job->company_id,
        'job_id' => $job->id,
        'criterion_id' => $criterionA->id,
        'weight' => 4,
    ]);

    JobCriterion::query()->create([
        'company_id' => $job->company_id,
        'job_id' => $job->id,
        'criterion_id' => $criterionB->id,
        'weight' => 9,
    ]);

    expect($job->criteria()->count())->toBe(2);
});

it('round-trips the pivot weight through the job\'s criteria relation', function () {
    $job = Job::factory()->create();
    $criterion = Criterion::factory()->for($job->company)->create();

    JobCriterion::query()->create([
        'company_id' => $job->company_id,
        'job_id' => $job->id,
        'criterion_id' => $criterion->id,
        'weight' => 7,
    ]);

    $attachedCriterion = $job->criteria()->first();

    expect($attachedCriterion->pivot->weight)->toBe(7);
});

it('round-trips the weight through the JobCriterion model directly', function () {
    $job = Job::factory()->create();
    $criterion = Criterion::factory()->for($job->company)->create();

    $jobCriterion = JobCriterion::query()->create([
        'company_id' => $job->company_id,
        'job_id' => $job->id,
        'criterion_id' => $criterion->id,
        'weight' => 3,
    ]);

    expect($jobCriterion->fresh()->weight)->toBe(3)
        ->and($jobCriterion->fresh()->weight)->toBeInt()
        ->and(JobCriterion::query()->find($jobCriterion->id)->job->is($job))->toBeTrue()
        ->and(JobCriterion::query()->find($jobCriterion->id)->criterion->is($criterion))->toBeTrue();
});

it('enforces uniqueness of the job_id/criterion_id pair at the database level', function () {
    $job = Job::factory()->create();
    $criterion = Criterion::factory()->for($job->company)->create();

    JobCriterion::query()->create([
        'company_id' => $job->company_id,
        'job_id' => $job->id,
        'criterion_id' => $criterion->id,
        'weight' => 5,
    ]);

    expect(fn () => JobCriterion::query()->create([
        'company_id' => $job->company_id,
        'job_id' => $job->id,
        'criterion_id' => $criterion->id,
        'weight' => 8,
    ]))->toThrow(QueryException::class);

    expect(JobCriterion::query()->count())->toBe(1);
});
