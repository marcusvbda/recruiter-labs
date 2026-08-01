<?php

use App\Models\Company;
use App\Models\Criterion;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Note: unlike the Job/Candidate/Referral tenant-isolation tests, `Criterion`
// has no Filament resource yet, so there is no Livewire page whose tenant
// scoping can be exercised. These tests instead cover the model-level
// scoping (the `company_id` foreign key and the `Company::criteria()` /
// `Criterion::company()` relations) that any future Filament resource for
// this model would rely on for tenant isolation.

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

it('scopes a company\'s criteria relation to its own records only', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $criterionA = Criterion::factory()->for($companyA)->create();
    $criterionB = Criterion::factory()->for($companyB)->create();

    $companyACriteria = $companyA->criteria()->pluck('id');

    expect($companyACriteria)->toContain($criterionA->id)
        ->and($companyACriteria)->not->toContain($criterionB->id)
        ->and($companyACriteria)->toHaveCount(1);
});

it('resolves the owning company from a criterion record', function () {
    $company = Company::factory()->create();
    $criterion = Criterion::factory()->for($company)->create();

    expect($criterion->company->is($company))->toBeTrue();
});

it('does not let a query scoped to one company return another company\'s criterion', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $criterionB = Criterion::factory()->for($companyB)->create();

    $result = Criterion::query()->where('company_id', $companyA->id)->find($criterionB->id);

    expect($result)->toBeNull();
});
