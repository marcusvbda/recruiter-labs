<?php

use App\Filament\Pages\Settings;
use App\Models\Company;
use App\Models\Plan;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Note: the `actAsCompany()` helper used below is declared once, globally, in
// tests/Pest.php and shared across tenant-isolation test files.

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

it('does not register the settings page in the sidebar navigation', function () {
    expect(Settings::shouldRegisterNavigation())->toBeFalse();
});

it('still resolves the settings page route for direct/topbar access', function () {
    $company = Company::factory()->create(['plan_id' => Plan::default()->id]);
    $user = actAsCompany($company);

    $response = $this->actingAs($user)->get(Settings::getUrl(tenant: $company));

    $response->assertOk();
});
