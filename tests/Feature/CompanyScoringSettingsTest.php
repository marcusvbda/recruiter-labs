<?php

use App\Actions\UpdateCompanyScoringSettings;
use App\Enums\ApplicationSource;
use App\Models\Application;
use App\Models\Company;
use App\Models\CompanyScoringSetting;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

it('returns null when the application has no analysis score yet', function () {
    $setting = CompanyScoringSetting::factory()->make();
    $application = Application::factory()->make(['analysis_score' => null]);

    expect($setting->overallScore($application))->toBeNull();
});

it('blends the analysis score with the referral bonus for a referral application using default weights', function () {
    $setting = CompanyScoringSetting::factory()->make([
        'analysis_weight' => 60,
        'referral_weight' => 40,
    ]);
    $application = Application::factory()->make([
        'analysis_score' => '80.00',
        'source' => ApplicationSource::Referral,
    ]);

    // (80 * 60 + 100 * 40) / 100 = (4800 + 4000) / 100 = 88.0
    expect($setting->overallScore($application))->toBe(88.0);
});

it('applies no referral bonus for a direct application using default weights', function () {
    $setting = CompanyScoringSetting::factory()->make([
        'analysis_weight' => 60,
        'referral_weight' => 40,
    ]);
    $application = Application::factory()->make([
        'analysis_score' => '80.00',
        'source' => ApplicationSource::Direct,
    ]);

    // (80 * 60 + 0 * 40) / 100 = 4800 / 100 = 48.0
    expect($setting->overallScore($application))->toBe(48.0);
});

it('uses the stored weights rather than hardcoded defaults', function () {
    $setting = CompanyScoringSetting::factory()->make([
        'analysis_weight' => 70,
        'referral_weight' => 30,
    ]);
    $application = Application::factory()->make([
        'analysis_score' => '80.00',
        'source' => ApplicationSource::Referral,
    ]);

    // (80 * 70 + 100 * 30) / 100 = (5600 + 3000) / 100 = 86.0
    expect($setting->overallScore($application))->toBe(86.0);
});

it('creates the scoring settings row with the given weights', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create();
    $company->users()->attach($user);

    $setting = app(UpdateCompanyScoringSettings::class)->run(
        company: $company,
        changedBy: $user,
        analysisWeight: 70,
        referralWeight: 30,
    );

    expect($setting->analysis_weight)->toBe(70)
        ->and($setting->referral_weight)->toBe(30)
        ->and(CompanyScoringSetting::query()->whereBelongsTo($company)->count())->toBe(1);
});

it('updates an existing scoring settings row instead of duplicating it', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create();
    $company->users()->attach($user);
    CompanyScoringSetting::factory()->for($company)->create([
        'analysis_weight' => 60,
        'referral_weight' => 40,
    ]);

    $setting = app(UpdateCompanyScoringSettings::class)->run(
        company: $company,
        changedBy: $user,
        analysisWeight: 20,
        referralWeight: 80,
    );

    expect($setting->analysis_weight)->toBe(20)
        ->and($setting->referral_weight)->toBe(80)
        ->and(CompanyScoringSetting::query()->whereBelongsTo($company)->count())->toBe(1);
});

it('rejects weights that do not sum to 100', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create();
    $company->users()->attach($user);

    expect(fn () => app(UpdateCompanyScoringSettings::class)->run(
        company: $company,
        changedBy: $user,
        analysisWeight: 60,
        referralWeight: 50,
    ))->toThrow(InvalidArgumentException::class);

    expect(CompanyScoringSetting::query()->whereBelongsTo($company)->exists())->toBeFalse();
});

it('rejects a weight outside the 0-100 range', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create();
    $company->users()->attach($user);

    expect(fn () => app(UpdateCompanyScoringSettings::class)->run(
        company: $company,
        changedBy: $user,
        analysisWeight: 120,
        referralWeight: -20,
    ))->toThrow(InvalidArgumentException::class);

    expect(CompanyScoringSetting::query()->whereBelongsTo($company)->exists())->toBeFalse();
});

it('rejects scoring changes from a user outside the company', function () {
    $company = Company::factory()->create();
    $outsider = User::factory()->create();

    expect(fn () => app(UpdateCompanyScoringSettings::class)->run(
        company: $company,
        changedBy: $outsider,
        analysisWeight: 60,
        referralWeight: 40,
    ))->toThrow(AuthorizationException::class);

    expect(CompanyScoringSetting::query()->whereBelongsTo($company)->exists())->toBeFalse();
});
