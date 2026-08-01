<?php

use App\Models\Application;
use Database\Seeders\PlanSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Note: `Application` has no Filament resource yet (unlike `Referral`), so
// there is no Livewire "create" form whose validation-level uniqueness
// error can be exercised. This file therefore only covers the DB-level
// constraint that `ReferralUniquenessTest` also asserts.

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

it('enforces uniqueness of the job_id/candidate_id pair at the database level', function () {
    $application = Application::factory()->create();

    expect(fn () => Application::query()->insert([
        'company_id' => $application->company_id,
        'job_id' => $application->job_id,
        'candidate_id' => $application->candidate_id,
        'status_id' => $application->status_id,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('allows the same candidate to apply to different jobs', function () {
    $application = Application::factory()->create();

    $secondApplication = Application::factory()
        ->for($application->company)
        ->for($application->candidate)
        ->create();

    expect($secondApplication->job_id)->not->toBe($application->job_id)
        ->and(Application::query()->count())->toBe(2);
});

it('allows different candidates to apply to the same job', function () {
    $application = Application::factory()->create();

    $secondApplication = Application::factory()
        ->for($application->company)
        ->for($application->job)
        ->create();

    expect($secondApplication->candidate_id)->not->toBe($application->candidate_id)
        ->and(Application::query()->count())->toBe(2);
});

it('rolls back the entire insert when the unique constraint is violated', function () {
    $application = Application::factory()->create();

    try {
        Application::query()->insert([
            'company_id' => $application->company_id,
            'job_id' => $application->job_id,
            'candidate_id' => $application->candidate_id,
            'status_id' => $application->status_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    } catch (QueryException) {
        // Expected: the DB-level unique constraint rejects the duplicate.
    }

    expect(Application::query()->count())->toBe(1);
});
