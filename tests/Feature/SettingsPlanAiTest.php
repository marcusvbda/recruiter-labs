<?php

use App\Enums\AiCredentialStatus;
use App\Enums\AiProvider;
use App\Filament\Pages\Settings;
use App\Models\Company;
use App\Models\Plan;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

it('opens the settings section requested by the topbar link', function (string $section) {
    $company = Company::factory()->create([
        'plan_id' => Plan::query()->where('slug', 'starter')->sole()->id,
    ]);
    actAsCompany($company);

    Livewire::withQueryParams(['section' => $section])
        ->test(Settings::class)
        ->assertSet('activeSettingsTab', $section);
})->with(['plan', 'ai']);

it('falls back to general settings for an unknown section', function () {
    $company = Company::factory()->create([
        'plan_id' => Plan::query()->where('slug', 'starter')->sole()->id,
    ]);
    actAsCompany($company);

    Livewire::withQueryParams(['section' => 'unknown'])
        ->test(Settings::class)
        ->assertSet('activeSettingsTab', 'general');
});

it('presents every plan, highlights the current plan, and includes centralized usage', function () {
    $company = Company::factory()->create([
        'plan_id' => Plan::query()->where('slug', 'starter')->sole()->id,
    ]);
    actAsCompany($company);

    $component = Livewire::test(Settings::class)
        ->set('activeSettingsTab', 'plan');
    $state = $component->instance()->planSettings;

    expect($state['current_plan']['slug'])->toBe('starter')
        ->and(array_column($state['plans'], 'slug'))->toBe(['starter', 'pro', 'business'])
        ->and(collect($state['plans'])->firstWhere('slug', 'starter')['is_current'])->toBeTrue()
        ->and(collect($state['plans'])->firstWhere('slug', 'pro')['direction'])->toBe('upgrade')
        ->and(array_column($state['usage'], 'key'))->toEqualCanonicalizing([
            'users',
            'jobs',
            'applications',
            'ai_analyses',
        ]);

    $component
        ->assertSet('activeSettingsTab', 'plan')
        ->assertSeeHtml('data-testid="plan-settings"')
        ->assertSeeHtml('data-testid="plan-card-starter"')
        ->assertSeeHtml('data-testid="plan-card-pro"')
        ->assertSeeHtml('data-testid="plan-card-business"')
        ->assertSeeHtml('data-testid="current-usage"')
        ->assertSeeHtml('data-testid="choose-plan-pro"')
        ->assertSeeHtml("wire:click=\"mountAction(&#039;changePlan&#039;, { plan: {$state['plans'][1]['id']} })\"")
        ->assertDontSee('@js', escape: false);
});

it('changes plan immediately when it is selected and refreshes page state', function () {
    $starter = Plan::query()->where('slug', 'starter')->sole();
    $pro = Plan::query()->where('slug', 'pro')->sole();
    $company = Company::factory()->create(['plan_id' => $starter->id]);
    actAsCompany($company);

    $component = Livewire::withQueryParams(['section' => 'plan'])
        ->test(Settings::class)
        ->mountAction('changePlan', ['plan' => $pro->id])
        ->assertDispatched('refresh-topbar');

    expect($company->fresh()->plan->is($pro))->toBeTrue()
        ->and($component->instance()->planSettings['current_plan']['slug'])->toBe('pro')
        ->and(collect($component->instance()->planSettings['plans'])->firstWhere('slug', 'starter')['direction'])->toBe('downgrade');
});

it('shows own OpenAI credentials as plan-locked with an AI usage empty state', function () {
    $company = Company::factory()->create([
        'plan_id' => Plan::query()->where('slug', 'starter')->sole()->id,
    ]);
    actAsCompany($company);

    $component = Livewire::test(Settings::class)
        ->set('activeSettingsTab', 'ai');
    $state = $component->instance()->aiSettings;

    expect($state['provider'])->toBe(AiProvider::Platform->value)
        ->and($state['own_key_allowed'])->toBeFalse()
        ->and($state['own_key_required_plan'])->toBe('Pro')
        ->and($state['has_own_key'])->toBeFalse()
        ->and($state['history'])->toBe([]);

    $component
        ->assertSet('activeSettingsTab', 'ai')
        ->assertSeeHtml('data-testid="ai-settings"')
        ->assertSeeHtml('data-testid="ai-usage"')
        ->assertSeeHtml('data-testid="ai-usage-history"')
        ->assertSee(__('settings.ai.history.empty_heading'))
        ->assertSee(__('settings.ai.own_key.locked'));
});

it('configures and validates an own key through Settings without exposing it in Livewire state', function () {
    Http::fake([
        'api.openai.com/*' => Http::response(['id' => 'gpt-4o-mini']),
    ]);
    $company = Company::factory()->create([
        'plan_id' => Plan::query()->where('slug', 'pro')->sole()->id,
    ]);
    actAsCompany($company);

    $component = Livewire::withQueryParams(['section' => 'ai'])
        ->test(Settings::class)
        ->callAction('configureOwnAi', [
            'api_key' => 'sk-settings-secret-4321',
            'model' => 'gpt-4o-mini',
        ])
        ->assertDispatched('refresh-topbar');

    $setting = $company->fresh()->aiSetting;

    expect($setting->provider)->toBe(AiProvider::Own)
        ->and($setting->credential_status)->toBe(AiCredentialStatus::Active)
        ->and($component->instance()->aiSettings['masked_key'])->toBe('sk-••••••••••••4321')
        ->and(json_encode($component->instance()->aiSettings))->not->toContain('sk-settings-secret-4321')
        ->and(json_encode($component->instance()->data))->not->toContain('sk-settings-secret-4321');
});

it('localizes the plan and AI settings surfaces', function (
    string $locale,
    string $planLabel,
    string $aiLabel,
    string $usageLabel,
) {
    $company = Company::factory()->create();
    $user = actAsCompany($company);
    $user->update(['locale' => $locale]);
    app()->setLocale($locale);

    Livewire::test(Settings::class)
        ->set('activeSettingsTab', 'plan')
        ->assertSee($planLabel)
        ->assertSee($aiLabel)
        ->assertSee($usageLabel);
})->with([
    'English' => ['en', 'Plan', 'AI', 'Current usage'],
    'Brazilian Portuguese' => ['pt_BR', 'Plano', 'IA', 'Uso atual'],
    'Spanish' => ['es', 'Plan', 'IA', 'Uso actual'],
]);
