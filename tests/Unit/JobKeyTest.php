<?php

use App\Models\Company;
use App\Models\Job;
use Database\Seeders\PlanSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

it('auto-generates a unique key when a job is created', function () {
    $job = Job::factory()->create();

    expect($job->key)->not->toBeNull();
    expect(Str::isUuid($job->key))->toBeTrue();
});

it('does not allow the key to be set via mass assignment', function () {
    $job = Job::create([
        'company_id' => Company::factory()->create()->id,
        'name' => 'Backend Engineer',
        'key' => 'not-a-real-key',
    ]);

    expect($job->key)->not->toBe('not-a-real-key');
    expect(Str::isUuid($job->key))->toBeTrue();
});

it('generates a different key for every job', function () {
    $jobA = Job::factory()->create();
    $jobB = Job::factory()->create();

    expect($jobA->key)->not->toBe($jobB->key);
});

it('enforces uniqueness of the key at the database level', function () {
    $job = Job::factory()->create();

    expect(fn () => Job::query()->insert([
        'company_id' => $job->company_id,
        'name' => 'Duplicate Key Job',
        'key' => $job->key,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('does not change the key when the job is updated', function () {
    $job = Job::factory()->create();
    $originalKey = $job->key;

    $job->update(['name' => 'Updated Name']);

    expect($job->fresh()->key)->toBe($originalKey);
});
