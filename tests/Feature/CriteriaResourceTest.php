<?php

use App\Filament\Resources\Criteria\Pages\CreateCriterion;
use App\Filament\Resources\Criteria\Pages\EditCriterion;
use App\Filament\Resources\Criteria\Pages\ListCriteria;
use App\Models\Company;
use App\Models\Criterion;
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

it('lists criteria belonging to the current tenant', function () {
    $company = Company::factory()->create();
    $otherCriterion = Criterion::factory()->create();

    actAsCompany($company);

    $criterion = Criterion::factory()->for($company)->create();

    Livewire::test(ListCriteria::class)
        ->assertCanSeeTableRecords([$criterion])
        ->assertCanNotSeeTableRecords([$otherCriterion]);
});

it('creates a criterion scoped to the current tenant', function () {
    $company = Company::factory()->create();
    actAsCompany($company);

    Livewire::test(CreateCriterion::class)
        ->fillForm([
            'name' => 'Communication skills',
            'prompt' => 'Evaluate how clearly the candidate communicates.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $criterion = Criterion::query()->where('name', 'Communication skills')->sole();

    expect($criterion->company_id)->toBe($company->id)
        ->and($criterion->prompt)->toBe('Evaluate how clearly the candidate communicates.');
});

it('requires name and prompt when creating a criterion', function () {
    $company = Company::factory()->create();
    actAsCompany($company);

    Livewire::test(CreateCriterion::class)
        ->fillForm(['name' => '', 'prompt' => ''])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required', 'prompt' => 'required']);
});

it('edits an existing criterion', function () {
    $company = Company::factory()->create();
    $criterion = Criterion::factory()->for($company)->create();
    actAsCompany($company);

    Livewire::test(EditCriterion::class, ['record' => $criterion->getRouteKey()])
        ->fillForm([
            'name' => 'Updated criterion name',
            'prompt' => 'Updated prompt text.',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($criterion->fresh()->name)->toBe('Updated criterion name')
        ->and($criterion->fresh()->prompt)->toBe('Updated prompt text.');
});
