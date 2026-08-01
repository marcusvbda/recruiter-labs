<?php

use App\Filament\Resources\EmailTemplates\Pages\CreateEmailTemplate;
use App\Filament\Resources\EmailTemplates\Pages\EditEmailTemplate;
use App\Filament\Resources\EmailTemplates\Pages\ListEmailTemplates;
use App\Models\Company;
use App\Models\EmailTemplate;
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

it('lists email templates belonging to the current tenant', function () {
    $company = Company::factory()->create();
    $otherTemplate = EmailTemplate::factory()->create();

    actAsCompany($company);

    $template = EmailTemplate::factory()->for($company)->create();

    Livewire::test(ListEmailTemplates::class)
        ->assertCanSeeTableRecords([$template])
        ->assertCanNotSeeTableRecords([$otherTemplate]);
});

it('creates an email template scoped to the current tenant', function () {
    $company = Company::factory()->create();
    actAsCompany($company);

    Livewire::test(CreateEmailTemplate::class)
        ->fillForm([
            'name' => 'Interview invitation',
            'subject' => 'You are invited to interview',
            'body' => 'Hello, we would like to invite you to an interview.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $template = EmailTemplate::query()->where('name', 'Interview invitation')->sole();

    expect($template->company_id)->toBe($company->id)
        ->and($template->subject)->toBe('You are invited to interview')
        ->and($template->body)->toBe('Hello, we would like to invite you to an interview.');
});

it('requires name, subject and body when creating an email template', function () {
    $company = Company::factory()->create();
    actAsCompany($company);

    Livewire::test(CreateEmailTemplate::class)
        ->fillForm(['name' => '', 'subject' => '', 'body' => ''])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required', 'subject' => 'required', 'body' => 'required']);
});

it('edits an existing email template', function () {
    $company = Company::factory()->create();
    $template = EmailTemplate::factory()->for($company)->create();
    actAsCompany($company);

    Livewire::test(EditEmailTemplate::class, ['record' => $template->getRouteKey()])
        ->fillForm([
            'name' => 'Updated template name',
            'subject' => 'Updated subject',
            'body' => 'Updated body content.',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($template->fresh()->name)->toBe('Updated template name')
        ->and($template->fresh()->subject)->toBe('Updated subject')
        ->and($template->fresh()->body)->toBe('Updated body content.');
});
