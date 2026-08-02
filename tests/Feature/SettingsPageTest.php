<?php

use App\Filament\Pages\Settings;
use App\Models\Company;
use App\Models\Plan;
use Database\Seeders\PlanSeeder;
use Filament\Support\Icons\Heroicon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

// Note: the `actAsCompany()` helper used below is declared once, globally, in
// tests/Pest.php and shared across tenant-isolation test files.

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

it('registers the settings page in the sidebar with its own icon', function () {
    expect(Settings::shouldRegisterNavigation())->toBeTrue()
        ->and(Settings::getNavigationIcon())->toBe(Heroicon::OutlinedCog6Tooth)
        ->and(Settings::getNavigationSort())->toBe(3);
});

it('still resolves the settings page route for direct/topbar access', function () {
    $company = Company::factory()->create(['plan_id' => Plan::default()->id]);
    $user = actAsCompany($company);

    $response = $this->actingAs($user)->get(Settings::getUrl(tenant: $company));

    $response->assertOk();
});

it('binds the settings form to the authenticated user as its singular record', function () {
    $company = Company::factory()->create(['plan_id' => Plan::default()->id]);
    $user = actAsCompany($company);

    $component = Livewire::test(Settings::class);

    expect($component->instance()->getRecord()->is($user))->toBeTrue()
        ->and($component->instance()->form->getRecord()->is($user))->toBeTrue()
        ->and($component->instance()->getView())->toBe('filament-panels::pages.page');
});

it('updates the authenticated user through the singular settings form', function () {
    $company = Company::factory()->create(['plan_id' => Plan::default()->id]);
    $user = actAsCompany($company);

    Livewire::test(Settings::class)
        ->fillForm([
            'name' => 'Updated User',
            'email' => 'updated@example.com',
            'locale' => 'es',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh())
        ->name->toBe('Updated User')
        ->email->toBe('updated@example.com')
        ->locale->toBe('es');
});
