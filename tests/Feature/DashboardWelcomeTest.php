<?php

use App\Filament\Pages\Dashboard;
use App\Models\Company;
use App\Models\Plan;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

it('welcomes the authenticated user in the selected company without dashboard metrics', function () {
    Carbon::setTestNow('2026-08-01 09:30:15');
    app()->setLocale('en');

    $company = Company::factory()->create([
        'name' => 'Gravity Labs',
        'plan_id' => Plan::default()->id,
    ]);
    $user = actAsCompany($company);
    $user->update([
        'name' => 'Maria Bassalobre',
        'email' => 'maria@example.com',
    ]);

    expect((new Dashboard)->getWidgets())->toBe([]);

    Livewire::test(Dashboard::class)
        ->assertSee('Good morning')
        ->assertSee('Maria')
        ->assertSee('Maria Bassalobre')
        ->assertSee('maria@example.com')
        ->assertSee('Gravity Labs')
        ->assertSee('Local date and time')
        ->assertSee('Quick access')
        ->assertSeeHtml('data-clock-time')
        ->assertDontSee('Candidate funnel')
        ->assertDontSee('Job applications');
});
