<?php

use App\Filament\Pages\Tenancy\EditCompanyProfile;
use App\Filament\Pages\Tenancy\RegisterCompany;
use App\Http\Middleware\ApplyTenantScopes;
use App\Models\Application;
use App\Models\Company;
use App\Models\Job;
use App\Models\JobCriterion;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

it('configures companies using the documented Filament tenancy pattern', function () {
    $panel = Filament::getPanel('admin');

    expect($panel->getTenantModel())->toBe(Company::class)
        ->and($panel->getTenantSlugAttribute())->toBe('slug')
        ->and($panel->getTenantRegistrationPage())->toBe(RegisterCompany::class)
        ->and($panel->getTenantProfilePage())->toBe(EditCompanyProfile::class)
        ->and($panel->getTenantMiddleware())->toContain(ApplyTenantScopes::class);
});

it('only allows users to access companies they belong to', function () {
    $ownCompany = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = User::factory()->create();
    $user->companies()->attach($ownCompany);

    expect($user->getTenants(Filament::getPanel('admin'))->modelKeys())->toBe([$ownCompany->id])
        ->and($user->canAccessTenant($ownCompany))->toBeTrue()
        ->and($user->canAccessTenant($otherCompany))->toBeFalse();

    $this->actingAs($user)
        ->get(route('filament.admin.pages.dashboard', ['tenant' => $otherCompany]))
        ->assertNotFound();
});

it('scopes tenant-owned models without resources on panel requests', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();

    $job = Job::factory()->for($company)->create();
    $jobCriterion = JobCriterion::query()->create([
        'company_id' => $company->id,
        'job_id' => $job->id,
        'prompt' => 'Evaluate communication skills.',
        'weight' => 5,
    ]);
    $application = Application::factory()->for($company)->create();

    $otherJob = Job::factory()->for($otherCompany)->create();
    JobCriterion::query()->create([
        'company_id' => $otherCompany->id,
        'job_id' => $otherJob->id,
        'prompt' => 'Evaluate leadership skills.',
        'weight' => 5,
    ]);
    Application::factory()->for($otherCompany)->create();

    $user = User::factory()->create();
    $user->companies()->attach($company);

    $this->actingAs($user)
        ->get(route('filament.admin.pages.dashboard', ['tenant' => $company]))
        ->assertOk();

    expect(Application::query()->pluck('id')->all())->toBe([$application->id])
        ->and(JobCriterion::query()->pluck('id')->all())->toBe([$jobCriterion->id]);
});
