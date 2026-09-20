<?php

use App\Data\EmailTemplateContext;
use App\Filament\Resources\EmailTemplates\Pages\CreateEmailTemplate;
use App\Filament\Resources\EmailTemplates\Pages\EditEmailTemplate;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\EmailTemplate;
use App\Models\Job;
use App\Models\Plan;
use App\Services\EmailTemplateRenderer;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Plan::query()->firstOrCreate(
        ['slug' => 'starter'],
        ['name' => 'Starter', 'sort_order' => 1, 'features' => [], 'limits' => []],
    );
});

/** Puts a signed-in recruiter inside a workspace, as the panel would. */
function emailTemplateWorkspace(): Company
{
    $company = Company::factory()->create();

    actAsCompany($company);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($company, isQuiet: true);
    Filament::bootCurrentPanel();

    return $company;
}

test('a template renders from a candidate and job without any application', function (): void {
    $company = Company::factory()->create();
    $candidate = Candidate::factory()->create(['company_id' => $company->getKey(), 'name' => 'Alex Candidate']);
    $job = Job::factory()->create(['company_id' => $company->getKey(), 'name' => 'Senior Engineer']);

    $template = EmailTemplate::factory()->create([
        'company_id' => $company->getKey(),
        'subject' => 'Your application for {{ job.title }}',
        'body' => '<p>Hi {{ candidate.name }}, from {{ company.name }}.</p>',
    ]);

    $renderer = app(EmailTemplateRenderer::class);
    $context = EmailTemplateContext::forCandidate($candidate, $job, $company);

    expect($renderer->renderSubject($template, $context))->toBe('Your application for Senior Engineer')
        ->and($renderer->renderBody($template, $context))
        ->toBe('<p>Hi Alex Candidate, from '.e($company->name).'.</p>')
        ->and($renderer->unresolvedTokens($template->subject, $context))->toBe([]);
});

test('a job token is reported as unresolved and rendered empty when no job is in context', function (): void {
    $company = Company::factory()->create();
    $candidate = Candidate::factory()->create(['company_id' => $company->getKey(), 'name' => 'Alex Candidate']);

    $renderer = app(EmailTemplateRenderer::class);
    $context = EmailTemplateContext::forCandidate($candidate, company: $company);

    expect($renderer->render('Role: {{ job.title }}', $context))->toBe('Role: ')
        ->and($renderer->unresolvedTokens('Role: {{ job.title }}', $context))->toBe(['job.title']);
});

test('the preview resolves tokens against sample values', function (): void {
    expect(app(EmailTemplateRenderer::class)->preview('Hi {{ candidate.name }}'))
        ->toBe('Hi '.__('pipelines.variables.samples.candidate_name'));
});

test('a recruiter can create and edit a workspace email template', function (): void {
    $company = emailTemplateWorkspace();

    Livewire::test(CreateEmailTemplate::class)
        ->fillForm([
            'name' => 'Interview invitation',
            'subject' => 'Interview for {{ job.title }}',
            'body' => '<p>Hi {{ candidate.name }}</p>',
            'is_available' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $template = EmailTemplate::query()->firstOrFail();

    expect($template->company_id)->toBe($company->getKey())
        ->and($template->is_available)->toBeTrue();

    Livewire::test(EditEmailTemplate::class, ['record' => $template->getKey()])
        ->fillForm(['is_available' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($template->refresh()->is_available)->toBeFalse()
        ->and(EmailTemplate::query()->available()->count())->toBe(0);
});
