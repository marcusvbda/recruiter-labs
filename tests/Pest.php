<?php

use App\Models\Company;
use App\Models\User;
use Filament\Facades\Filament;

/**
 * Log in as a user belonging to the given company and activate it as the
 * current Filament tenant. Must be called *after* any cross-tenant fixture
 * data has been created: once the tenant is active, Filament's tenancy
 * observer stamps every newly created owned model with the current tenant,
 * which would silently overwrite an explicitly assigned `company_id`.
 *
 * Shared across tenant-isolation tests (e.g. `CandidateTenantIsolationTest`,
 * `JobTenantIsolationTest`) so it is declared once, globally, here rather
 * than duplicated per test file.
 */
function actAsCompany(Company $company): User
{
    $user = User::factory()->create();
    $user->companies()->attach($company);

    test()->actingAs($user);

    Filament::setTenant($company);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();

    return $user;
}
