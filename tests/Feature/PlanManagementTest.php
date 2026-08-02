<?php

use App\Actions\ChangeCompanyPlan;
use App\Enums\Feature;
use App\Enums\Limit;
use App\Enums\PlanChangeSource;
use App\Models\Company;
use App\Models\Job;
use App\Models\Plan;
use App\Models\PlanChange;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

it('seeds the development plans and uses starter as the default plan', function () {
    $starter = Plan::query()->where('slug', 'starter')->sole();
    $pro = Plan::query()->where('slug', 'pro')->sole();
    $business = Plan::query()->where('slug', 'business')->sole();

    expect(Plan::query()->pluck('slug')->all())
        ->toEqualCanonicalizing(['starter', 'pro', 'business'])
        ->and(Plan::default()->is($starter))->toBeTrue()
        ->and($starter->getLimit(Limit::Users))->toBe(2)
        ->and($starter->getLimit(Limit::Jobs))->toBe(1)
        ->and($starter->getLimit(Limit::Applications))->toBe(30)
        ->and($starter->getLimit(Limit::AiAnalyses))->toBe(20)
        ->and($starter->hasFeature(Feature::OwnAiKey))->toBeFalse()
        ->and($pro->getLimit(Limit::Users))->toBe(10)
        ->and($pro->getLimit(Limit::Jobs))->toBe(20)
        ->and($pro->getLimit(Limit::Applications))->toBe(1000)
        ->and($pro->getLimit(Limit::AiAnalyses))->toBe(1000)
        ->and($pro->hasFeature(Feature::OwnAiKey))->toBeTrue()
        ->and($business->hasFeature(Feature::OwnAiKey))->toBeTrue();
});

it('supports unlimited limits on business without treating them as reached', function () {
    $business = Plan::query()->where('slug', 'business')->sole();

    expect($business->getLimit(Limit::Users))->toBeNull()
        ->and($business->getLimit(Limit::Jobs))->toBeNull()
        ->and($business->getLimit(Limit::Applications))->toBeNull()
        ->and($business->getLimit(Limit::AiAnalyses))->toBeNull();
});

it('changes plans immediately and records the manual source and responsible user', function () {
    $starter = Plan::query()->where('slug', 'starter')->sole();
    $pro = Plan::query()->where('slug', 'pro')->sole();
    $company = Company::factory()->create(['plan_id' => $starter->id]);
    $user = User::factory()->create();
    $company->users()->attach($user);

    $change = app(ChangeCompanyPlan::class)->run(
        company: $company,
        newPlan: $pro,
        changedBy: $user,
    );

    expect($change)->toBeInstanceOf(PlanChange::class)
        ->and($company->fresh()->plan->is($pro))->toBeTrue()
        ->and($company->fresh()->getLimit(Limit::Users))->toBe(10)
        ->and($change->source)->toBe(PlanChangeSource::ManualSettings)
        ->and($change->company->is($company))->toBeTrue()
        ->and($change->previousPlan->is($starter))->toBeTrue()
        ->and($change->newPlan->is($pro))->toBeTrue()
        ->and($change->changedBy->is($user))->toBeTrue();
});

it('preserves existing data while a downgrade applies lower limits immediately', function () {
    $starter = Plan::query()->where('slug', 'starter')->sole();
    $pro = Plan::query()->where('slug', 'pro')->sole();
    $company = Company::factory()->create(['plan_id' => $pro->id]);
    $user = User::factory()->create();
    $company->users()->attach($user);
    Job::factory()->count(4)->for($company)->create(['published' => true]);

    app(ChangeCompanyPlan::class)->run($company, $starter, $user);

    expect($company->fresh()->jobs)->toHaveCount(4)
        ->and($company->fresh()->getLimit(Limit::Jobs))->toBe(1);
});

it('rejects a plan change by a user outside the company', function () {
    $company = Company::factory()->create([
        'plan_id' => Plan::query()->where('slug', 'starter')->sole()->id,
    ]);
    $outsider = User::factory()->create();
    $pro = Plan::query()->where('slug', 'pro')->sole();

    expect(fn () => app(ChangeCompanyPlan::class)->run($company, $pro, $outsider))
        ->toThrow(AuthorizationException::class);

    expect($company->fresh()->plan->slug)->toBe('starter')
        ->and(PlanChange::query()->whereBelongsTo($company)->exists())->toBeFalse();
});

it('records optional metadata without changing another tenant', function () {
    $starter = Plan::query()->where('slug', 'starter')->sole();
    $pro = Plan::query()->where('slug', 'pro')->sole();
    $company = Company::factory()->create(['plan_id' => $starter->id]);
    $otherCompany = Company::factory()->create(['plan_id' => $starter->id]);
    $user = User::factory()->create();
    $company->users()->attach($user);

    $change = app(ChangeCompanyPlan::class)->run(
        company: $company,
        newPlan: $pro,
        changedBy: $user,
        source: PlanChangeSource::ManualSettings,
        metadata: ['surface' => 'settings'],
    );

    expect($change->metadata)->toBe(['surface' => 'settings'])
        ->and($company->fresh()->plan->is($pro))->toBeTrue()
        ->and($otherCompany->fresh()->plan->is($starter))->toBeTrue();
});
