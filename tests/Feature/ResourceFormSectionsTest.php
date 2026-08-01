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
    'job' => [CreateJob::class, ['Job details', 'Campaign', 'Evaluation criteria']],
    'referral' => [CreateReferral::class, ['Referral details']],
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
        __('jobs.sections.campaign'),
        __('jobs.sections.criteria'),
        __('referrals.sections.details'),
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
        'Campaign',
        'Evaluation criteria',
        'Referral details',
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
        'Campaña',
        'Criterios de evaluación',
        'Detalles de la referencia',
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
        'Campanha',
        'Critérios de avaliação',
        'Detalhes da indicação',
        'Detalhes do modelo',
        'Conteúdo da mensagem',
        'Gatilho',
        'Ação',
    ]],
]);
