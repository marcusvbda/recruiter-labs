<?php

use App\Filament\Pages\Tenancy\RegisterCompany;
use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

it('redirects a user with no companies to the company registration page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin')
        ->assertRedirect(route('filament.admin.tenant.registration'));
});

it('does not redirect a user who already belongs to a company', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create();
    $user->companies()->attach($company);

    $this->actingAs($user)
        ->get('/admin')
        ->assertRedirect(route('filament.admin.pages.dashboard', ['tenant' => $company]));
});

it('auto-fills the slug from the company name while keeping it editable', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(RegisterCompany::class)
        ->fillForm(['name' => 'Acme Recruiting'])
        ->assertSet('data.slug', 'acme-recruiting')
        ->set('data.slug', 'acme-recruiting-custom')
        ->assertSet('data.slug', 'acme-recruiting-custom');
});

it('creates the company and attaches the registering user on registration', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(RegisterCompany::class)
        ->fillForm(['name' => 'Acme Recruiting'])
        ->call('register')
        ->assertHasNoFormErrors();

    $company = Company::query()->where('slug', 'acme-recruiting')->sole();

    expect($user->fresh()->companies->pluck('id')->all())->toBe([$company->id])
        ->and($company->plan_id)->toBe(Plan::default()->id);
});

it('does not allow registering a company with a slug that is already taken', function () {
    Company::factory()->create(['slug' => 'acme-recruiting']);

    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(RegisterCompany::class)
        ->fillForm(['name' => 'Another Name', 'slug' => 'acme-recruiting'])
        ->call('register')
        ->assertHasFormErrors(['slug' => 'unique']);

    expect($user->fresh()->companies)->toBeEmpty();
});
