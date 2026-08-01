<?php

use App\Filament\Resources\Jobs\Pages\CreateJob;
use App\Filament\Resources\Jobs\Pages\EditJob;
use App\Models\Company;
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

it('aligns the criteria weight slider and fills its selected track', function () {
    $company = Company::factory()->create();
    actAsCompany($company);

    Livewire::test(CreateJob::class)
        ->fillForm([
            'jobCriteria' => [
                ['prompt' => 'Evaluate communication clarity.', 'weight' => 5],
            ],
        ])
        ->assertSeeHtml('margin-block: 0.625rem;')
        ->assertSeeHtml("fillTrack: JSON.parse('[true,false]')");
});

it('creates a job with campaign fields and job criteria repeater rows', function () {
    $company = Company::factory()->create();
    actAsCompany($company);

    Livewire::test(CreateJob::class)
        ->fillForm([
            'name' => 'Senior Backend Engineer',
            'description' => 'We are hiring for a senior backend role.',
            'starts_at' => '2026-08-01',
            'ends_at' => '2026-09-01',
            'campaign_expectation' => 'Expect to hire 2 engineers meeting at least 80% of criteria.',
            'jobCriteria' => [
                ['prompt' => 'Evaluate how clearly the candidate communicates.', 'weight' => 4],
                ['prompt' => 'Evaluate the candidate\'s ability to design reliable APIs.', 'weight' => 9],
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

    $rowForA = $pivotRows->firstWhere('prompt', 'Evaluate how clearly the candidate communicates.');
    $rowForB = $pivotRows->firstWhere('prompt', 'Evaluate the candidate\'s ability to design reliable APIs.');

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

    Livewire::test(CreateJob::class)
        ->fillForm([
            'name' => 'Product Designer',
            'jobCriteria' => [
                ['prompt' => 'Evaluate product design ability.', 'weight' => 15],
            ],
        ])
        ->call('create')
        ->assertHasFormErrors(['jobCriteria.0.weight']);
});

it('rejects a job criteria prompt longer than 150 characters', function () {
    $company = Company::factory()->create();
    actAsCompany($company);

    Livewire::test(CreateJob::class)
        ->fillForm([
            'name' => 'Product Designer',
            'jobCriteria' => [
                ['prompt' => str_repeat('a', 151), 'weight' => 5],
            ],
        ])
        ->call('create')
        ->assertHasFormErrors(['jobCriteria.0.prompt' => 'max']);
});

it('adds job criteria rows to an existing job through the edit form', function () {
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create();
    actAsCompany($company);

    Livewire::test(EditJob::class, ['record' => $job->getRouteKey()])
        ->fillForm([
            'jobCriteria' => [
                ['prompt' => 'Evaluate the candidate\'s leadership skills.', 'weight' => 7],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $pivotRow = JobCriterion::query()->where('job_id', $job->id)->sole();

    expect($pivotRow->company_id)->toBe($company->id)
        ->and($pivotRow->prompt)->toBe('Evaluate the candidate\'s leadership skills.')
        ->and($pivotRow->weight)->toBe(7);
});
