<?php

use App\Filament\Resources\Jobs\Pages\CreateJob;
use App\Filament\Resources\Jobs\Pages\EditJob;
use App\Models\Company;
use App\Models\Criterion;
use App\Models\Job;
use App\Models\JobCriterion;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

// Note: the `actAsCompany()` helper used below is declared once, globally, in
// tests/Pest.php and shared across tenant-isolation test files.

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

it('creates a job with campaign fields and job criteria repeater rows', function () {
    $company = Company::factory()->create();
    actAsCompany($company);

    $criterionA = Criterion::factory()->for($company)->create();
    $criterionB = Criterion::factory()->for($company)->create();

    Livewire::test(CreateJob::class)
        ->fillForm([
            'name' => 'Senior Backend Engineer',
            'description' => 'We are hiring for a senior backend role.',
            'starts_at' => '2026-08-01',
            'ends_at' => '2026-09-01',
            'campaign_expectation' => 'Expect to hire 2 engineers meeting at least 80% of criteria.',
            'jobCriteria' => [
                ['criterion_id' => $criterionA->id, 'weight' => 4],
                ['criterion_id' => $criterionB->id, 'weight' => 9],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $job = Job::query()->where('name', 'Senior Backend Engineer')->sole();

    expect($job->company_id)->toBe($company->id)
        ->and($job->description)->toBe('We are hiring for a senior backend role.')
        ->and($job->starts_at->toDateString())->toBe('2026-08-01')
        ->and($job->ends_at->toDateString())->toBe('2026-09-01')
        ->and($job->campaign_expectation)->toBe('Expect to hire 2 engineers meeting at least 80% of criteria.');

    $pivotRows = JobCriterion::query()->where('job_id', $job->id)->get();

    expect($pivotRows)->toHaveCount(2);

    $rowForA = $pivotRows->firstWhere('criterion_id', $criterionA->id);
    $rowForB = $pivotRows->firstWhere('criterion_id', $criterionB->id);

    expect($rowForA)->not->toBeNull()
        ->and($rowForA->company_id)->toBe($company->id)
        ->and($rowForA->weight)->toBe(4)
        ->and($rowForB)->not->toBeNull()
        ->and($rowForB->company_id)->toBe($company->id)
        ->and($rowForB->weight)->toBe(9);
});

it('rejects a job criteria weight outside the 0-10 range', function () {
    $company = Company::factory()->create();
    actAsCompany($company);

    $criterion = Criterion::factory()->for($company)->create();

    Livewire::test(CreateJob::class)
        ->fillForm([
            'name' => 'Product Designer',
            'jobCriteria' => [
                ['criterion_id' => $criterion->id, 'weight' => 15],
            ],
        ])
        ->call('create')
        ->assertHasFormErrors(['jobCriteria.0.weight']);
});

it('adds job criteria rows to an existing job through the edit form', function () {
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create();
    $criterion = Criterion::factory()->for($company)->create();
    actAsCompany($company);

    Livewire::test(EditJob::class, ['record' => $job->getRouteKey()])
        ->fillForm([
            'jobCriteria' => [
                ['criterion_id' => $criterion->id, 'weight' => 7],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $pivotRow = JobCriterion::query()->where('job_id', $job->id)->sole();

    expect($pivotRow->company_id)->toBe($company->id)
        ->and($pivotRow->criterion_id)->toBe($criterion->id)
        ->and($pivotRow->weight)->toBe(7);
});
