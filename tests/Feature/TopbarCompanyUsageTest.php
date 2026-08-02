<?php

use App\Actions\ChangeCompanyPlan;
use App\Enums\AiProvider;
use App\Enums\AiUsageStatus;
use App\Enums\Limit;
use App\Enums\UsageWarningState;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Settings;
use App\Models\AiUsageRecord;
use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use App\Services\CompanyTopbarSummary;
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

function createTopbarAiUsage(Company $company, int $count): void
{
    AiUsageRecord::factory()->count($count)->create([
        'company_id' => $company->id,
        'user_id' => null,
        'application_id' => null,
        'operation' => 'candidate_analysis',
        'provider' => AiProvider::Platform,
        'model' => 'gpt-4o-mini',
        'input_tokens' => 100,
        'output_tokens' => 20,
        'cached_tokens' => 0,
        'estimated_cost' => '0.001000',
        'duration_ms' => 250,
        'status' => AiUsageStatus::Completed,
        'used_own_key' => false,
        'created_at' => '2026-08-10 10:00:00',
    ]);
}

it('builds a compact topbar summary from one centralized usage result', function () {
    config(['services.openai.api_key' => 'sk-platform-secret']);
    $plan = Plan::query()->where('slug', 'starter')->sole();
    $plan->update([
        'limits' => [...$plan->limits, Limit::AiAnalyses->value => 10],
    ]);
    $company = Company::factory()->create(['plan_id' => $plan->id]);
    createTopbarAiUsage($company, 8);

    $summary = app(CompanyTopbarSummary::class)->for($company);

    expect($summary->planName)->toBe('Starter')
        ->and($summary->planSlug)->toBe('starter')
        ->and($summary->planLimits[Limit::Users->value])->toBe(2)
        ->and($summary->aiUsage->used)->toBe(8)
        ->and($summary->aiUsage->limitValue)->toBe(10)
        ->and($summary->aiUsage->percentage)->toBe(80)
        ->and($summary->aiUsage->warningState)->toBe(UsageWarningState::Attention)
        ->and($summary->provider)->toBe(AiProvider::Platform)
        ->and($summary->model)->toBe('gpt-4o-mini');
});

it('reports unlimited AI usage in the topbar summary', function () {
    $company = Company::factory()->create([
        'plan_id' => Plan::query()->where('slug', 'business')->sole()->id,
    ]);
    createTopbarAiUsage($company, 3);

    $summary = app(CompanyTopbarSummary::class)->for($company);

    expect($summary->aiUsage->used)->toBe(3)
        ->and($summary->aiUsage->isUnlimited)->toBeTrue()
        ->and($summary->aiUsage->limitValue)->toBeNull()
        ->and($summary->aiUsage->warningState)->toBe(UsageWarningState::Normal);
});

it('can invalidate its per-request tenant memoization after a plan change', function () {
    $starter = Plan::query()->where('slug', 'starter')->sole();
    $pro = Plan::query()->where('slug', 'pro')->sole();
    $company = Company::factory()->create(['plan_id' => $starter->id]);
    $user = User::factory()->create();
    $company->users()->attach($user);
    $service = app(CompanyTopbarSummary::class);

    expect($service->for($company)->planSlug)->toBe('starter');

    app(ChangeCompanyPlan::class)->run($company, $pro, $user);
    $service->forget($company);

    expect($service->for($company)->planSlug)->toBe('pro')
        ->and($service->for($company)->aiUsage->limitValue)->toBe(1000);
});

it('renders plan and AI links with an accessible warning state in the authenticated topbar', function (
    int $used,
    string $warningState,
    string $statusTranslation,
) {
    $plan = Plan::query()->where('slug', 'starter')->sole();
    $plan->update([
        'limits' => [...$plan->limits, Limit::AiAnalyses->value => 10],
    ]);
    $company = Company::factory()->create(['plan_id' => $plan->id]);
    createTopbarAiUsage($company, $used);
    $user = actAsCompany($company);

    $response = $this->actingAs($user)->get(Dashboard::getUrl(tenant: $company));

    $response->assertOk()
        ->assertSee('data-testid="company-topbar-summary"', escape: false)
        ->assertSee("data-warning=\"{$warningState}\"", escape: false)
        ->assertSee("{$used} / 10")
        ->assertSee(__($statusTranslation))
        ->assertSee(Settings::getUrl(['section' => 'plan'], tenant: $company), escape: false)
        ->assertSee(Settings::getUrl(['section' => 'ai'], tenant: $company), escape: false);
})->with([
    'attention at eighty percent' => [8, 'attention', 'settings.topbar.warning'],
    'critical at ninety percent' => [9, 'critical', 'settings.topbar.critical'],
    'reached at one hundred percent' => [10, 'reached', 'settings.topbar.reached'],
]);
