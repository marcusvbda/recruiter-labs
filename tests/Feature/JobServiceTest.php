<?php

use App\Models\Job;
use App\Services\JobService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    $this->travelTo('2026-08-15 12:00:00');
});

afterEach(function () {
    $this->travelBack();
});

it('retrieves a published job within its optional inclusive campaign dates', function (?string $startsAt, ?string $endsAt) {
    $job = Job::factory()->create([
        'published' => true,
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
    ]);

    $retrievedJob = app(JobService::class)->retrieve($job->key);

    expect($retrievedJob)->not->toBeNull()
        ->and($retrievedJob->is($job))->toBeTrue();
})->with([
    'inside window' => ['2026-08-01', '2026-08-31'],
    'starts today' => ['2026-08-15', '2026-08-31'],
    'ends today' => ['2026-08-01', '2026-08-15'],
    'without start date' => [null, '2026-08-31'],
    'without end date' => ['2026-08-01', null],
    'without campaign dates' => [null, null],
]);

it('does not retrieve an unavailable job', function (array $attributes) {
    $job = Job::factory()->create([
        'published' => true,
        'starts_at' => '2026-08-01',
        'ends_at' => '2026-08-31',
        ...$attributes,
    ]);

    expect(app(JobService::class)->retrieve($job->key))->toBeNull();
})->with([
    'unpublished' => [['published' => false]],
    'not started' => [['starts_at' => '2026-08-16']],
    'ended' => [['ends_at' => '2026-08-14']],
]);

it('does not retrieve a malformed key', function () {
    expect(app(JobService::class)->retrieve('unknown-key'))->toBeNull();
});

it('does not retrieve an unknown UUID', function () {
    expect(app(JobService::class)->retrieve((string) Str::uuid()))->toBeNull();
});

it('returns 404 from the public job route when the job is unavailable', function () {
    $job = Job::factory()->create([
        'published' => false,
        'starts_at' => '2026-08-01',
        'ends_at' => '2026-08-31',
    ]);

    $this->get(route('job.show', ['key' => $job->key]))
        ->assertNotFound();
});
