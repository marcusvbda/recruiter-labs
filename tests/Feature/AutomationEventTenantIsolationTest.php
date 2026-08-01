<?php

use App\Models\AutomationEvent;
use App\Models\Company;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Note: unlike the Job/Candidate/Referral tenant-isolation tests,
// `AutomationEvent` has no Filament resource yet, so there is no Livewire
// page whose tenant scoping can be exercised. These tests instead cover the
// model-level scoping (the `company_id` foreign key and the
// `Company::automationEvents()` / `AutomationEvent::company()` relations)
// that any future Filament resource for this model would rely on for
// tenant isolation.

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

it('scopes a company\'s automation events relation to its own records only', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $eventA = AutomationEvent::factory()->for($companyA)->create();
    $eventB = AutomationEvent::factory()->for($companyB)->create();

    $companyAEvents = $companyA->automationEvents()->pluck('id');

    expect($companyAEvents)->toContain($eventA->id)
        ->and($companyAEvents)->not->toContain($eventB->id)
        ->and($companyAEvents)->toHaveCount(1);
});

it('resolves the owning company from an automation event record', function () {
    $company = Company::factory()->create();
    $event = AutomationEvent::factory()->for($company)->create();

    expect($event->company->is($company))->toBeTrue();
});

it('does not let a query scoped to one company return another company\'s automation event', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $eventB = AutomationEvent::factory()->for($companyB)->create();

    $result = AutomationEvent::query()->where('company_id', $companyA->id)->find($eventB->id);

    expect($result)->toBeNull();
});
