<?php

use App\Enums\AiProvider;
use App\Enums\AiUsageStatus;
use App\Enums\Limit;
use App\Enums\UsageWarningState;
use App\Exceptions\PlanLimitExceededException;
use App\Models\AiUsageRecord;
use App\Models\Company;
use App\Models\Plan;
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

function createAiUsageForTest(
    Company $company,
    AiProvider $provider = AiProvider::Platform,
    bool $usedOwnKey = false,
    string $createdAt = '2026-08-10 10:00:00',
): AiUsageRecord {
    return AiUsageRecord::factory()->create([
        'company_id' => $company->id,
        'user_id' => null,
        'application_id' => null,
        'operation' => 'candidate_analysis',
        'provider' => $provider,
        'model' => 'gpt-4o-mini',
        'input_tokens' => 120,
        'output_tokens' => 30,
        'cached_tokens' => 10,
        'estimated_cost' => '0.001250',
        'duration_ms' => 350,
        'status' => AiUsageStatus::Completed,
        'used_own_key' => $usedOwnKey,
        'created_at' => $createdAt,
    ]);
}

it('enforces the monthly AI analysis limit', function () {
    $plan = Plan::query()->where('slug', 'starter')->sole();
    $plan->update([
        'limits' => [...$plan->limits, Limit::AiAnalyses->value => 2],
    ]);
    $company = Company::factory()->create(['plan_id' => $plan->id]);
    createAiUsageForTest($company);
    createAiUsageForTest($company);
    createAiUsageForTest($company, createdAt: '2026-07-15 10:00:00');

    expect(fn () => app(LimitManager::class)->ensureCanRunAiAnalysis($company))
        ->toThrow(PlanLimitExceededException::class);
});

it('derives accessible warning states at each consumption threshold', function (
    int $used,
    UsageWarningState $expectedState,
    bool $isReached,
) {
    $plan = Plan::query()->where('slug', 'starter')->sole();
    $plan->update([
        'limits' => [...$plan->limits, Limit::AiAnalyses->value => 10],
    ]);
    $company = Company::factory()->create(['plan_id' => $plan->id]);

    foreach (range(1, $used) as $index) {
        createAiUsageForTest($company, createdAt: "2026-08-{$index} 10:00:00");
    }

    $metric = app(CompanyUsageService::class)->usageFor($company, Limit::AiAnalyses);

    expect($metric->warningState)->toBe($expectedState)
        ->and($metric->isReached)->toBe($isReached);
})->with([
    'normal below eighty percent' => [7, UsageWarningState::Normal, false],
    'attention at eighty percent' => [8, UsageWarningState::Attention, false],
    'critical at ninety percent' => [9, UsageWarningState::Critical, false],
    'reached at one hundred percent' => [10, UsageWarningState::Reached, true],
]);

it('reports unlimited AI usage while retaining provider analytics', function () {
    $company = Company::factory()->create([
        'plan_id' => Plan::query()->where('slug', 'business')->sole()->id,
    ]);
    createAiUsageForTest($company);
    createAiUsageForTest($company, AiProvider::Own, true);

    $summary = app(CompanyUsageService::class)->summary($company);
    $metric = $summary->metric(Limit::AiAnalyses);

    expect($metric->used)->toBe(1)
        ->and($metric->limitValue)->toBeNull()
        ->and($metric->remaining)->toBeNull()
        ->and($metric->isUnlimited)->toBeTrue()
        ->and($metric->isReached)->toBeFalse()
        ->and($summary->platformAiAnalyses)->toBe(1)
        ->and($summary->ownAiAnalyses)->toBe(1);

    app(LimitManager::class)->ensureCanRunAiAnalysis($company);
});
