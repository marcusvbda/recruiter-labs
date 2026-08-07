<?php

use App\Filament\Pages\Settings;
use App\Models\Company;
use App\Models\CompanyScoringSetting;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

it('shows the default scoring weights when the company has no scoring settings row yet', function () {
    $company = Company::factory()->create();
    actAsCompany($company);

    $component = Livewire::withQueryParams(['section' => 'scoring'])
        ->test(Settings::class);

    expect($component->instance()->scoringSettings)->toBe([
        'analysis_weight' => 60,
        'referral_weight' => 40,
    ]);

    $component
        ->assertSet('activeSettingsTab', 'scoring')
        ->assertSeeHtml('data-testid="scoring-settings"')
        ->assertSeeHtml('data-testid="scoring-weights"')
        ->assertSee('60%')
        ->assertSee('40%');
});

it('shows the stored scoring weights when the company already has a scoring settings row', function () {
    $company = Company::factory()->create();
    CompanyScoringSetting::factory()->for($company)->create([
        'analysis_weight' => 70,
        'referral_weight' => 30,
    ]);
    actAsCompany($company);

    $component = Livewire::withQueryParams(['section' => 'scoring'])
        ->test(Settings::class);

    expect($component->instance()->scoringSettings)->toBe([
        'analysis_weight' => 70,
        'referral_weight' => 30,
    ]);

    $component->assertSee('70%')->assertSee('30%');
});

it('updates the scoring weights and reflects the change on the next render', function () {
    $company = Company::factory()->create();
    actAsCompany($company);

    Livewire::withQueryParams(['section' => 'scoring'])
        ->test(Settings::class)
        ->callAction('updateScoringWeights', [
            'analysis_weight' => 70,
            'referral_weight' => 30,
        ])
        ->assertNotified(__('settings.notifications.scoring_updated'));

    $setting = CompanyScoringSetting::query()->whereBelongsTo($company)->sole();

    expect($setting->analysis_weight)->toBe(70)
        ->and($setting->referral_weight)->toBe(30);

    Livewire::withQueryParams(['section' => 'scoring'])
        ->test(Settings::class)
        ->assertSee('70%')
        ->assertSee('30%');
});

it('rejects scoring weights that do not sum to 100 and keeps the current settings unchanged', function () {
    $company = Company::factory()->create();
    CompanyScoringSetting::factory()->for($company)->create([
        'analysis_weight' => 60,
        'referral_weight' => 40,
    ]);
    actAsCompany($company);

    $component = Livewire::withQueryParams(['section' => 'scoring'])
        ->test(Settings::class)
        ->callAction('updateScoringWeights', [
            'analysis_weight' => 60,
            'referral_weight' => 50,
        ]);

    $component->assertNotified('The analysis and referral weights must sum to exactly 100.');

    $setting = CompanyScoringSetting::query()->whereBelongsTo($company)->sole();

    expect($setting->analysis_weight)->toBe(60)
        ->and($setting->referral_weight)->toBe(40)
        ->and($component->instance()->scoringSettings)->toBe([
            'analysis_weight' => 60,
            'referral_weight' => 40,
        ]);
});
