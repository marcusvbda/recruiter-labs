<?php

use App\Filament\Resources\AutomationEvents\Pages\CreateAutomationEvent;
use App\Filament\Resources\Candidates\Pages\CreateCandidate;
use App\Filament\Resources\EmailTemplates\Pages\CreateEmailTemplate;
use App\Filament\Resources\Jobs\Pages\CreateJob;
use App\Filament\Resources\Referrals\Pages\CreateReferral;
use App\Filament\Resources\Statuses\Pages\CreateStatus;
use App\Models\Company;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

it('groups resource fields into named section cards', function (string $page, array $headings) {
    actAsCompany(Company::factory()->create());

    $component = Livewire::test($page)
        ->assertSeeHtml('fi-section-has-header');

    foreach ($headings as $heading) {
        $component->assertSee($heading);
    }
})->with([
    'candidate' => [CreateCandidate::class, ['Contact information', 'Social profiles']],
    'status' => [CreateStatus::class, ['Status details']],
    'job' => [CreateJob::class, ['Job details', 'Application page', 'Campaign']],
    'referral' => [CreateReferral::class, ['Referral details', 'Link availability']],
    'email template' => [CreateEmailTemplate::class, ['Template details', 'Message content']],
    'event hook' => [CreateAutomationEvent::class, ['Trigger', 'Action']],
]);

it('translates every resource section heading', function (string $locale, array $headings) {
    app()->setLocale($locale);

    expect([
        __('candidates.sections.contact'),
        __('candidates.sections.social_profiles'),
        __('statuses.sections.details'),
        __('jobs.sections.details'),
        __('jobs.sections.application'),
        __('jobs.application.campaign_section'),
        __('jobs.criteria.section_title'),
        __('referrals.sections.details'),
        __('referrals.sections.availability'),
        __('email-templates.sections.details'),
        __('email-templates.sections.content'),
        __('event-hooks.sections.trigger'),
        __('event-hooks.sections.action'),
    ])->toBe($headings);
})->with([
    'English' => ['en', [
        'Contact information',
        'Social profiles',
        'Status details',
        'Job details',
        'Application page',
        'Campaign',
        'Candidate evaluation criteria',
        'Referral details',
        'Link availability',
        'Template details',
        'Message content',
        'Trigger',
        'Action',
    ]],
    'Spanish' => ['es', [
        'Información de contacto',
        'Perfiles sociales',
        'Detalles del estado',
        'Detalles del empleo',
        'Página de postulación',
        'Campaña',
        'Criterios de evaluación de candidatos',
        'Detalles de la referencia',
        'Disponibilidad del enlace',
        'Detalles de la plantilla',
        'Contenido del mensaje',
        'Disparador',
        'Acción',
    ]],
    'Brazilian Portuguese' => ['pt_BR', [
        'Informações de contato',
        'Perfis sociais',
        'Detalhes do status',
        'Detalhes da vaga',
        'Página de candidatura',
        'Campanha',
        'Critérios de avaliação de candidatos',
        'Detalhes da indicação',
        'Disponibilidade do link',
        'Detalhes do modelo',
        'Conteúdo da mensagem',
        'Gatilho',
        'Ação',
    ]],
]);
