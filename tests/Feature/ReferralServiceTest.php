<?php

use App\Models\Job;
use App\Models\Referral;
use App\Services\ReferralService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    $this->travelTo('2026-08-15 12:00:00');
});

afterEach(function () {
    $this->travelBack();
});

it('retrieves a referral for a published job within its optional inclusive dates', function (?string $startsAt, ?string $endsAt) {
    $job = Job::factory()->create([
        'published' => true,
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
    ]);
    $referral = Referral::factory()
        ->for($job->company)
        ->for($job)
        ->create();

    $retrievedReferral = app(ReferralService::class)->retrieve($referral->key);

    expect($retrievedReferral)->not->toBeNull()
        ->and($retrievedReferral->is($referral))->toBeTrue();
})->with([
    'inside window' => ['2026-08-01', '2026-08-31'],
    'starts today' => ['2026-08-15', '2026-08-31'],
    'ends today' => ['2026-08-01', '2026-08-15'],
    'without start date' => [null, '2026-08-31'],
    'without end date' => ['2026-08-01', null],
    'without campaign dates' => [null, null],
]);

it('does not retrieve a referral for an unavailable job', function (array $attributes) {
    $job = Job::factory()->create([
        'published' => true,
        'starts_at' => '2026-08-01',
        'ends_at' => '2026-08-31',
        ...$attributes,
    ]);
    $referral = Referral::factory()
        ->for($job->company)
        ->for($job)
        ->create();

    expect(app(ReferralService::class)->retrieve($referral->key))->toBeNull();
})->with([
    'unpublished' => [['published' => false]],
    'not started' => [['starts_at' => '2026-08-16']],
    'ended' => [['ends_at' => '2026-08-14']],
]);

it('does not retrieve a malformed referral key', function () {
    expect(app(ReferralService::class)->retrieve('unknown-key'))->toBeNull();
});

it('does not retrieve an unknown referral UUID', function () {
    expect(app(ReferralService::class)->retrieve((string) Str::uuid()))->toBeNull();
});

it('returns 404 from the public referral route when its job is unavailable', function () {
    $job = Job::factory()->create([
        'published' => false,
        'starts_at' => '2026-08-01',
        'ends_at' => '2026-08-31',
    ]);
    $referral = Referral::factory()
        ->for($job->company)
        ->for($job)
        ->create();

    $this->get(route('referral.show', ['key' => $referral->key]))
        ->assertNotFound();
});

it('renders the referral application page for an available job', function () {
    $job = Job::factory()->create([
        'description' => '<p>Referral description</p><script>alert("unsafe")</script>',
        'published' => true,
        'starts_at' => '2026-08-01',
        'ends_at' => '2026-08-31',
    ]);
    $referral = Referral::factory()
        ->for($job->company)
        ->for($job)
        ->create();

    $this->get(route('referral.show', ['key' => $referral->key]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('job/apply')
            ->where('referral.id', $referral->id)
            ->where('job.id', $job->id)
            ->where('job.description', fn (string $description): bool => str_contains($description, '<p>Referral description</p>')
                && ! str_contains($description, '<script>')));
});
