<?php

use App\Filament\Resources\Jobs\Pages\EditJob;
use App\Filament\Resources\Jobs\Pages\ListJobs;
use App\Models\Company;
use App\Models\Job;
use App\Models\Plan;
use Database\Seeders\PlanSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

// Note: the `actAsCompany()` helper used below is declared once, globally, in
// tests/Pest.php and shared across tenant-isolation test files.

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

it('does not let a user from one company view a job belonging to another company', function () {
    $companyA = Company::factory()->create(['plan_id' => Plan::default()->id]);
    $companyB = Company::factory()->create(['plan_id' => Plan::default()->id]);
    $jobB = Job::factory()->for($companyB)->create();

    actAsCompany($companyA);

    expect(fn () => Livewire::test(EditJob::class, ['record' => $jobB->getRouteKey()]))
        ->toThrow(ModelNotFoundException::class);
});

it('does not let a user from one company edit a job belonging to another company', function () {
    $companyA = Company::factory()->create(['plan_id' => Plan::default()->id]);
    $companyB = Company::factory()->create(['plan_id' => Plan::default()->id]);
    $jobB = Job::factory()->for($companyB)->create(['name' => 'Original Name']);

    actAsCompany($companyA);

    expect(fn () => Livewire::test(EditJob::class, ['record' => $jobB->getRouteKey()])
        ->set('data.name', 'Tampered Name')
        ->call('save'))
        ->toThrow(ModelNotFoundException::class);

    expect($jobB->fresh()->name)->toBe('Original Name');
});

it('scopes the jobs list to the current tenant only', function () {
    $companyA = Company::factory()->create(['plan_id' => Plan::default()->id]);
    $companyB = Company::factory()->create(['plan_id' => Plan::default()->id]);
    $otherJob = Job::factory()->for($companyB)->create();

    actAsCompany($companyA);

    $ownJob = Job::factory()->for($companyA)->create();

    Livewire::test(ListJobs::class)
        ->assertCanSeeTableRecords([$ownJob])
        ->assertCanNotSeeTableRecords([$otherJob])
        ->assertCountTableRecords(1);
});
