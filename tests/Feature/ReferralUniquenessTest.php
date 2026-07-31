<?php

use App\Filament\Resources\Referrals\Pages\CreateReferral;
use App\Models\Company;
use App\Models\Job;
use App\Models\Plan;
use App\Models\Referral;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

// Note: the `actAsCompany()` helper used below is declared once, globally, in
// tests/Pest.php and shared across tenant-isolation test files.

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

it('enforces uniqueness of the job_id/user_id pair at the database level', function () {
    $referral = Referral::factory()->create();

    expect(fn () => Referral::query()->insert([
        'company_id' => $referral->company_id,
        'job_id' => $referral->job_id,
        'user_id' => $referral->user_id,
        'key' => (string) Str::uuid(),
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('rejects a duplicate job/user referral submission with a form validation error instead of a query exception', function () {
    $company = Company::factory()->create(['plan_id' => Plan::default()->id]);
    $job = Job::factory()->for($company)->create();
    $referredUser = User::factory()->create();
    $referredUser->companies()->attach($company);

    actAsCompany($company);

    Referral::factory()->for($company)->for($job)->for($referredUser)->create();

    Livewire::test(CreateReferral::class)
        ->fillForm([
            'job_id' => $job->id,
            'user_id' => $referredUser->id,
        ])
        ->call('create')
        ->assertHasFormErrors(['user_id' => 'unique']);

    expect(Referral::query()->count())->toBe(1);
});
