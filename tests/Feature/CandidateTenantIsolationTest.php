<?php

use App\Filament\Resources\Candidates\Pages\EditCandidate;
use App\Filament\Resources\Candidates\Pages\ListCandidates;
use App\Models\Candidate;
use App\Models\Company;
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

it('does not let a user from one company view a candidate belonging to another company', function () {
    $companyA = Company::factory()->create(['plan_id' => Plan::default()->id]);
    $companyB = Company::factory()->create(['plan_id' => Plan::default()->id]);
    $candidateB = Candidate::factory()->for($companyB)->create();

    actAsCompany($companyA);

    expect(fn () => Livewire::test(EditCandidate::class, ['record' => $candidateB->getRouteKey()]))
        ->toThrow(ModelNotFoundException::class);
});

it('does not let a user from one company edit a candidate belonging to another company', function () {
    $companyA = Company::factory()->create(['plan_id' => Plan::default()->id]);
    $companyB = Company::factory()->create(['plan_id' => Plan::default()->id]);
    $candidateB = Candidate::factory()->for($companyB)->create(['name' => 'Original Name']);

    actAsCompany($companyA);

    expect(fn () => Livewire::test(EditCandidate::class, ['record' => $candidateB->getRouteKey()])
        ->set('data.name', 'Tampered Name')
        ->call('save'))
        ->toThrow(ModelNotFoundException::class);

    expect($candidateB->fresh()->name)->toBe('Original Name');
});

it('scopes the candidates list to the current tenant only', function () {
    $companyA = Company::factory()->create(['plan_id' => Plan::default()->id]);
    $companyB = Company::factory()->create(['plan_id' => Plan::default()->id]);
    $otherCandidate = Candidate::factory()->for($companyB)->create();

    actAsCompany($companyA);

    $ownCandidate = Candidate::factory()->for($companyA)->create();

    Livewire::test(ListCandidates::class)
        ->assertCanSeeTableRecords([$ownCandidate])
        ->assertCanNotSeeTableRecords([$otherCandidate])
        ->assertCountTableRecords(1);
});
