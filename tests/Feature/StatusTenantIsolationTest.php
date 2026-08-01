<?php

use App\Models\Company;
use App\Models\Status;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Note: unlike the Job/Candidate/Referral tenant-isolation tests, `Status`
// has no Filament resource yet, so there is no Livewire page whose tenant
// scoping can be exercised. These tests instead cover the model-level
// scoping (the `company_id` foreign key and the `Company::statuses()` /
// `Status::company()` relations) that any future Filament resource for
// this model would rely on for tenant isolation.

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

it('scopes a company\'s statuses relation to its own records only', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $statusA = Status::factory()->for($companyA)->create();
    $statusB = Status::factory()->for($companyB)->create();

    $companyAStatuses = $companyA->statuses()->pluck('id');

    expect($companyAStatuses)->toContain($statusA->id)
        ->and($companyAStatuses)->not->toContain($statusB->id)
        ->and($companyAStatuses)->toHaveCount(1);
});

it('resolves the owning company from a status record', function () {
    $company = Company::factory()->create();
    $status = Status::factory()->for($company)->create();

    expect($status->company->is($company))->toBeTrue();
});

it('does not let a query scoped to one company return another company\'s status', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $statusB = Status::factory()->for($companyB)->create();

    $result = Status::query()->where('company_id', $companyA->id)->find($statusB->id);

    expect($result)->toBeNull();
});
