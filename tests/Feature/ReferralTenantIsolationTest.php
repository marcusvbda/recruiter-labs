<?php

use App\Filament\Resources\Referrals\Pages\EditReferral;
use App\Filament\Resources\Referrals\Pages\ListReferrals;
use App\Models\Company;
use App\Models\Job;
use App\Models\Plan;
use App\Models\Referral;
use App\Models\User;
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

it('does not let a user from one company view a referral belonging to another company', function () {
    $companyA = Company::factory()->create(['plan_id' => Plan::default()->id]);
    $companyB = Company::factory()->create(['plan_id' => Plan::default()->id]);
    $jobB = Job::factory()->for($companyB)->create();
    $userB = User::factory()->create();
    $referralB = Referral::factory()->for($companyB)->for($jobB)->for($userB)->create();

    actAsCompany($companyA);

    expect(fn () => Livewire::test(EditReferral::class, ['record' => $referralB->getRouteKey()]))
        ->toThrow(ModelNotFoundException::class);
});

it('does not let a user from one company edit a referral belonging to another company', function () {
    $companyA = Company::factory()->create(['plan_id' => Plan::default()->id]);
    $companyB = Company::factory()->create(['plan_id' => Plan::default()->id]);
    $jobB = Job::factory()->for($companyB)->create();
    $userB = User::factory()->create();
    $otherJobB = Job::factory()->for($companyB)->create();
    $referralB = Referral::factory()->for($companyB)->for($jobB)->for($userB)->create();

    actAsCompany($companyA);

    expect(fn () => Livewire::test(EditReferral::class, ['record' => $referralB->getRouteKey()])
        ->set('data.job_id', $otherJobB->id)
        ->call('save'))
        ->toThrow(ModelNotFoundException::class);

    expect($referralB->fresh()->job_id)->toBe($jobB->id);
});

it('scopes the referrals list to the current tenant only', function () {
    $companyA = Company::factory()->create(['plan_id' => Plan::default()->id]);
    $companyB = Company::factory()->create(['plan_id' => Plan::default()->id]);
    $jobB = Job::factory()->for($companyB)->create();
    $userB = User::factory()->create();
    $otherReferral = Referral::factory()->for($companyB)->for($jobB)->for($userB)->create();

    actAsCompany($companyA);

    $jobA = Job::factory()->for($companyA)->create();
    $userA = User::factory()->create();
    $ownReferral = Referral::factory()->for($companyA)->for($jobA)->for($userA)->create();

    Livewire::test(ListReferrals::class)
        ->assertCanSeeTableRecords([$ownReferral])
        ->assertCanNotSeeTableRecords([$otherReferral])
        ->assertCountTableRecords(1);
});
