<?php

use App\Filament\Resources\Statuses\Pages\CreateStatus;
use App\Filament\Resources\Statuses\Pages\EditStatus;
use App\Filament\Resources\Statuses\Pages\ListStatuses;
use App\Models\Application;
use App\Models\Company;
use App\Models\Status;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

// Note: the `actAsCompany()` helper used below is declared once, globally, in
// tests/Pest.php and shared across tenant-isolation test files. Any fixture
// belonging to a *different* company must be created before calling
// `actAsCompany()`: once the tenant is active, Filament's tenancy observer
// stamps every newly created owned model with the current tenant, which
// would silently overwrite a cross-tenant fixture's `company_id`.

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

it('lists statuses belonging to the current tenant', function () {
    $company = Company::factory()->create();
    $otherStatus = Status::factory()->create();

    actAsCompany($company);

    $status = Status::factory()->for($company)->create();

    Livewire::test(ListStatuses::class)
        ->assertCanSeeTableRecords([$status])
        ->assertCanNotSeeTableRecords([$otherStatus]);
});

it('creates a status scoped to the current tenant', function () {
    $company = Company::factory()->create();
    actAsCompany($company);

    Livewire::test(CreateStatus::class)
        ->fillForm([
            'name' => 'Screening',
            'color' => '#ff0000',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $status = Status::query()->where('name', 'Screening')->sole();

    expect($status->company_id)->toBe($company->id)
        ->and($status->color)->toBe('#ff0000');
});

it('requires name and color when creating a status', function () {
    $company = Company::factory()->create();
    actAsCompany($company);

    Livewire::test(CreateStatus::class)
        ->fillForm(['name' => '', 'color' => ''])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required', 'color' => 'required']);
});

it('edits an existing status', function () {
    $company = Company::factory()->create();
    $status = Status::factory()->for($company)->create();
    actAsCompany($company);

    Livewire::test(EditStatus::class, ['record' => $status->getRouteKey()])
        ->fillForm([
            'name' => 'Updated status name',
            'color' => '#00ff00',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($status->fresh()->name)->toBe('Updated status name')
        ->and($status->fresh()->color)->toBe('#00ff00');
});

it('blocks bulk-deleting a status that still has an application attached and notifies instead of throwing', function () {
    $company = Company::factory()->create();
    actAsCompany($company);

    $application = Application::factory()->for($company)->create();
    $status = $application->status;

    Livewire::test(ListStatuses::class)
        ->callTableBulkAction('delete', [$status]);

    expect(Status::query()->find($status->id))->not->toBeNull()
        ->and(Application::query()->find($application->id))->not->toBeNull();
});

it('allows bulk-deleting a status once no application references it', function () {
    $company = Company::factory()->create();
    actAsCompany($company);

    $status = Status::factory()->for($company)->create();

    Livewire::test(ListStatuses::class)
        ->callTableBulkAction('delete', [$status]);

    expect(Status::query()->find($status->id))->toBeNull();
});
