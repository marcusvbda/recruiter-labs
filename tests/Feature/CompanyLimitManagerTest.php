<?php

use App\Actions\ChangeCompanyPlan;
use App\Enums\Limit;
use App\Exceptions\PlanLimitExceededException;
use App\Models\Application;
use App\Models\Company;
use App\Models\Job;
use App\Models\Plan;
use App\Models\User;
use App\Services\CompanyUsageService;
use App\Services\LimitManager;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    $this->travelTo('2026-08-15 12:00:00');
});

afterEach(function () {
    $this->travelBack();
});

it('enforces the company user limit and exposes a stable domain error', function () {
    $company = Company::factory()->create([
        'plan_id' => Plan::query()->where('slug', 'starter')->sole()->id,
    ]);
    $company->users()->attach(User::factory()->count(2)->create());

    try {
        app(LimitManager::class)->ensureCanCreateUser($company);
        $this->fail('The user limit should have been reached.');
    } catch (PlanLimitExceededException $exception) {
        expect($exception->limit())->toBe(Limit::Users)
            ->and($exception->metric()->used)->toBe(2)
            ->and($exception->metric()->limitValue)->toBe(2)
            ->and($exception->errorCode())->toBe('plan_limit_exceeded.users');
    }
});

it('counts only currently active public jobs towards the job limit', function () {
    $plan = Plan::query()->where('slug', 'starter')->sole();
    $company = Company::factory()->create(['plan_id' => $plan->id]);

    Job::factory()->for($company)->create([
        'published' => false,
        'starts_at' => null,
        'ends_at' => null,
    ]);
    Job::factory()->for($company)->create([
        'published' => true,
        'starts_at' => '2026-08-16',
        'ends_at' => null,
    ]);
    Job::factory()->for($company)->create([
        'published' => true,
        'starts_at' => null,
        'ends_at' => '2026-08-14',
    ]);
    Job::factory()->count(3)->for($company)->create([
        'published' => true,
        'starts_at' => '2026-08-01',
        'ends_at' => '2026-08-31',
    ]);

    $metric = app(CompanyUsageService::class)->usageFor($company, Limit::Jobs);

    expect($metric->used)->toBe(3)
        ->and($metric->limitValue)->toBe(3)
        ->and($metric->remaining)->toBe(0)
        ->and($metric->percentage)->toBe(100)
        ->and($metric->isReached)->toBeTrue();

    expect(fn () => app(LimitManager::class)->ensureCanCreateJob($company))
        ->toThrow(PlanLimitExceededException::class);
});

it('enforces applications within the current monthly cycle only', function () {
    $plan = Plan::query()->where('slug', 'starter')->sole();
    $plan->update([
        'limits' => [...$plan->limits, Limit::Applications->value => 2],
    ]);
    $company = Company::factory()->create(['plan_id' => $plan->id]);

    Application::factory()->create([
        'company_id' => $company->id,
        'created_at' => '2026-07-31 23:59:59',
    ]);
    Application::factory()->count(2)->create([
        'company_id' => $company->id,
        'created_at' => '2026-08-10 09:00:00',
    ]);

    $metric = app(CompanyUsageService::class)->usageFor($company, Limit::Applications);

    expect($metric->used)->toBe(2)
        ->and($metric->cycleStart->toDateString())->toBe('2026-08-01')
        ->and($metric->cycleEnd->toDateString())->toBe('2026-08-31');

    expect(fn () => app(LimitManager::class)->ensureCanReceiveApplication($company))
        ->toThrow(PlanLimitExceededException::class);
});

it('does not block unlimited business plan limits', function () {
    $company = Company::factory()->create([
        'plan_id' => Plan::query()->where('slug', 'business')->sole()->id,
    ]);
    $company->users()->attach(User::factory()->count(15)->create());
    Job::factory()->count(25)->for($company)->create([
        'published' => true,
        'starts_at' => null,
        'ends_at' => null,
    ]);

    $userMetric = app(CompanyUsageService::class)->usageFor($company, Limit::Users);

    expect($userMetric->used)->toBe(15)
        ->and($userMetric->limitValue)->toBeNull()
        ->and($userMetric->remaining)->toBeNull()
        ->and($userMetric->percentage)->toBe(0)
        ->and($userMetric->isUnlimited)->toBeTrue()
        ->and($userMetric->isReached)->toBeFalse();

    app(LimitManager::class)->ensureCanCreateUser($company);
    app(LimitManager::class)->ensureCanCreateJob($company);

    expect(true)->toBeTrue();
});

it('blocks new actions when usage is already above a downgraded limit without deleting data', function () {
    $company = Company::factory()->create([
        'plan_id' => Plan::query()->where('slug', 'starter')->sole()->id,
    ]);
    Job::factory()->count(4)->for($company)->create([
        'published' => true,
        'starts_at' => null,
        'ends_at' => null,
    ]);

    $metric = app(CompanyUsageService::class)->usageFor($company, Limit::Jobs);

    expect($metric->used)->toBe(4)
        ->and($metric->limitValue)->toBe(3)
        ->and($metric->remaining)->toBe(0)
        ->and($metric->percentage)->toBeGreaterThan(100)
        ->and($company->jobs()->count())->toBe(4);

    expect(fn () => app(LimitManager::class)->ensureCanCreateJob($company))
        ->toThrow(PlanLimitExceededException::class);
});

it('keeps usage isolated between companies', function () {
    $starter = Plan::query()->where('slug', 'starter')->sole();
    $company = Company::factory()->create(['plan_id' => $starter->id]);
    $otherCompany = Company::factory()->create(['plan_id' => $starter->id]);
    Job::factory()->count(3)->for($otherCompany)->create([
        'published' => true,
        'starts_at' => null,
        'ends_at' => null,
    ]);

    $metric = app(CompanyUsageService::class)->usageFor($company, Limit::Jobs);

    expect($metric->used)->toBe(0)
        ->and($metric->isReached)->toBeFalse();

    app(LimitManager::class)->ensureCanCreateJob($company);
});

it('unblocks an action immediately after upgrading the current company plan', function () {
    $starter = Plan::query()->where('slug', 'starter')->sole();
    $pro = Plan::query()->where('slug', 'pro')->sole();
    $company = Company::factory()->create(['plan_id' => $starter->id]);
    $user = User::factory()->create();
    $company->users()->attach($user);
    Job::factory()->count(3)->for($company)->create([
        'published' => true,
        'starts_at' => null,
        'ends_at' => null,
    ]);

    expect(fn () => app(LimitManager::class)->ensureCanCreateJob($company))
        ->toThrow(PlanLimitExceededException::class);

    app(ChangeCompanyPlan::class)->run($company, $pro, $user);
    app(LimitManager::class)->ensureCanCreateJob($company->fresh());

    expect($company->fresh()->plan->is($pro))->toBeTrue();
});
