<?php

use App\Models\Company;
use App\Models\EmailTemplate;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Note: unlike the Job/Candidate/Referral tenant-isolation tests,
// `EmailTemplate` has no Filament resource yet, so there is no Livewire page
// whose tenant scoping can be exercised. These tests instead cover the
// model-level scoping (the `company_id` foreign key and the
// `Company::emailTemplates()` / `EmailTemplate::company()` relations) that
// any future Filament resource for this model would rely on for tenant
// isolation.

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

it('scopes a company\'s email templates relation to its own records only', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $templateA = EmailTemplate::factory()->for($companyA)->create();
    $templateB = EmailTemplate::factory()->for($companyB)->create();

    $companyATemplates = $companyA->emailTemplates()->pluck('id');

    expect($companyATemplates)->toContain($templateA->id)
        ->and($companyATemplates)->not->toContain($templateB->id)
        ->and($companyATemplates)->toHaveCount(1);
});

it('resolves the owning company from an email template record', function () {
    $company = Company::factory()->create();
    $template = EmailTemplate::factory()->for($company)->create();

    expect($template->company->is($company))->toBeTrue();
});

it('does not let a query scoped to one company return another company\'s email template', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $templateB = EmailTemplate::factory()->for($companyB)->create();

    $result = EmailTemplate::query()->where('company_id', $companyA->id)->find($templateB->id);

    expect($result)->toBeNull();
});
