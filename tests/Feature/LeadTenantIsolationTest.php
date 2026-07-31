<?php

use App\Filament\Resources\Leads\Pages\EditLead;
use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

/**
 * Log in as a user belonging to the given company and activate it as the
 * current Filament tenant. Must be called *after* any cross-tenant fixture
 * data has been created: once the tenant is active, Filament's tenancy
 * observer stamps every newly created owned model with the current tenant,
 * which would silently overwrite an explicitly assigned `company_id`.
 */
function actAsCompany(Company $company): User
{
    $user = User::factory()->create();
    $user->companies()->attach($company);

    test()->actingAs($user);

    Filament::setTenant($company);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();

    return $user;
}

it('does not let a user from one company view a lead belonging to another company', function () {
    $companyA = Company::factory()->create(['plan_id' => Plan::default()->id]);
    $companyB = Company::factory()->create(['plan_id' => Plan::default()->id]);
    $leadB = Lead::factory()->for($companyB)->create();

    actAsCompany($companyA);

    expect(fn () => Livewire::test(EditLead::class, ['record' => $leadB->getRouteKey()]))
        ->toThrow(ModelNotFoundException::class);
});

it('does not let a user from one company edit a lead belonging to another company', function () {
    $companyA = Company::factory()->create(['plan_id' => Plan::default()->id]);
    $companyB = Company::factory()->create(['plan_id' => Plan::default()->id]);
    $leadB = Lead::factory()->for($companyB)->create(['name' => 'Original Name']);

    actAsCompany($companyA);

    expect(fn () => Livewire::test(EditLead::class, ['record' => $leadB->getRouteKey()])
        ->set('data.name', 'Tampered Name')
        ->call('save'))
        ->toThrow(ModelNotFoundException::class);

    expect($leadB->fresh()->name)->toBe('Original Name');
});

it('scopes the leads list to the current tenant only', function () {
    $companyA = Company::factory()->create(['plan_id' => Plan::default()->id]);
    $companyB = Company::factory()->create(['plan_id' => Plan::default()->id]);
    $otherLead = Lead::factory()->for($companyB)->create();

    actAsCompany($companyA);

    $ownLead = Lead::factory()->for($companyA)->create();

    Livewire::test(ListLeads::class)
        ->assertCanSeeTableRecords([$ownLead])
        ->assertCanNotSeeTableRecords([$otherLead])
        ->assertCountTableRecords(1);
});
