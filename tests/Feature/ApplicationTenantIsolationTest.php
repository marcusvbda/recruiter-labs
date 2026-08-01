<?php

use App\Models\Application;
use App\Models\Company;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Note: unlike the Job/Candidate/Referral tenant-isolation tests,
// `Application` has no Filament resource yet, so there is no Livewire page
// whose tenant scoping can be exercised. These tests instead cover the
// model-level scoping (the `company_id` foreign key and the
// `Company::applications()` / `Application::company()` relations) that any
// future Filament resource for this model would rely on for tenant
// isolation.

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

it('scopes a company\'s applications relation to its own records only', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $applicationA = Application::factory()->for($companyA)->create();
    $applicationB = Application::factory()->for($companyB)->create();

    $companyAApplications = $companyA->applications()->pluck('id');

    expect($companyAApplications)->toContain($applicationA->id)
        ->and($companyAApplications)->not->toContain($applicationB->id)
        ->and($companyAApplications)->toHaveCount(1);
});

it('resolves the owning company from an application record', function () {
    $company = Company::factory()->create();
    $application = Application::factory()->for($company)->create();

    expect($application->company->is($company))->toBeTrue();
});

it('does not let a query scoped to one company return another company\'s application', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $applicationB = Application::factory()->for($companyB)->create();

    $result = Application::query()->where('company_id', $companyA->id)->find($applicationB->id);

    expect($result)->toBeNull();
});
