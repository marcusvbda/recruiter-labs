<?php

use App\Enums\ApplicationAnalysisStatus;
use App\Enums\ApplicationCoverLetterType;
use App\Enums\ApplicationDocumentType;
use App\Enums\ApplicationQuestionType;
use App\Enums\ApplicationSource;
use App\Filament\Resources\Applications\ApplicationResource;
use App\Filament\Resources\Applications\Pages\ViewApplication;
use App\Filament\Resources\Jobs\Widgets\JobPipelineKanban;
use App\Models\Application;
use App\Models\ApplicationAnswer;
use App\Models\ApplicationDocument;
use App\Models\ApplicationUtmParameter;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Job;
use App\Models\Referral;
use App\Models\Status;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

it('renders complete application details without exposing private storage paths', function () {
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create(['name' => 'Senior Platform Engineer']);
    $status = Status::factory()->for($company)->create(['name' => 'Screening']);
    $candidate = Candidate::factory()->for($company)->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'phone' => '+353871234567',
        'socials' => [[
            'network' => 'linkedin',
            'account' => 'https://www.linkedin.com/in/ada',
        ]],
    ]);
    $referrer = User::factory()->create(['name' => 'Grace Hopper']);
    $referrer->companies()->attach($company);
    $referral = Referral::factory()->for($company)->for($job)->for($referrer)->create();
    $application = Application::factory()->for($company)->create([
        'job_id' => $job->id,
        'candidate_id' => $candidate->id,
        'status_id' => $status->id,
        'referral_id' => $referral->id,
        'source' => ApplicationSource::Referral,
        'analysis_status' => ApplicationAnalysisStatus::Pending,
        'cover_letter_type' => ApplicationCoverLetterType::Text,
        'cover_letter_text' => 'I enjoy building reliable recruiting products.',
        'submitted_ip' => '203.0.113.15',
    ]);

    ApplicationAnswer::query()->create([
        'company_id' => $company->id,
        'application_id' => $application->id,
        'job_application_question_id' => null,
        'question_snapshot' => 'Describe a system you scaled.',
        'response_type' => ApplicationQuestionType::Textarea,
        'value_text' => 'I scaled a multi-tenant event platform.',
        'value_number' => null,
    ]);
    ApplicationDocument::query()->create([
        'company_id' => $company->id,
        'application_id' => $application->id,
        'type' => ApplicationDocumentType::Cv,
        'disk' => 'local',
        'path' => 'companies/private/application/resume.pdf',
        'original_name' => 'ada-resume.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'size' => 2048,
        'checksum' => hash('sha256', 'resume'),
        'uploaded_at' => now(),
    ]);
    ApplicationUtmParameter::query()->create([
        'application_id' => $application->id,
        'name' => 'utm_source',
        'value' => 'linkedin',
    ]);

    $admin = actAsCompany($company);

    expect(Gate::forUser($admin)->allows('view', $application))->toBeTrue();
    expect(ApplicationResource::canView($application))->toBeTrue();

    $response = $this->get(ApplicationResource::getUrl('view', [
        'record' => $application,
    ], tenant: $company));

    $response
        ->assertSuccessful()
        ->assertSeeLivewire(ViewApplication::class)
        ->assertSee('Ada Lovelace')
        ->assertSee('Senior Platform Engineer')
        ->assertSee('Screening')
        ->assertSee('Grace Hopper')
        ->assertSee('utm_source')
        ->assertSee('linkedin')
        ->assertSee('Describe a system you scaled.')
        ->assertSee('I scaled a multi-tenant event platform.')
        ->assertSee('I enjoy building reliable recruiting products.')
        ->assertSee('ada-resume.pdf')
        ->assertSee(__('applications.admin.tabs.ai_analysis'))
        ->assertSee(__('applications.admin.ai.states.pending.title'))
        ->assertDontSee('companies/private/application/resume.pdf')
        ->assertDontSee(hash('sha256', 'resume'));
});

it('does not resolve an application through another tenant resource URL', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $foreignApplication = Application::factory()->for($otherCompany)->create();

    actAsCompany($company);

    $this->get(ApplicationResource::getUrl('view', [
        'record' => $foreignApplication,
    ], tenant: $company))->assertNotFound();
});

it('denies an internally inconsistent application even to a company member', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $foreignJob = Job::factory()->for($otherCompany)->create();
    $application = Application::factory()->for($company)->create([
        'job_id' => $foreignJob->id,
    ]);
    $user = User::factory()->create();
    $user->companies()->attach($company);

    expect(Gate::forUser($user)->allows('view', $application))->toBeFalse();
});

it('moves an application only to a status from its own company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $currentStatus = Status::factory()->for($company)->create(['name' => 'Applied', 'order' => 1]);
    $nextStatus = Status::factory()->for($company)->create(['name' => 'Interview', 'order' => 2]);
    $foreignStatus = Status::factory()->for($otherCompany)->create(['name' => 'Foreign status']);
    $application = Application::factory()->for($company)->create(['status_id' => $currentStatus->id]);

    actAsCompany($company);

    Livewire::test(ViewApplication::class, ['record' => $application->getRouteKey()])
        ->callAction('moveStatus', ['status_id' => $nextStatus->id])
        ->assertNotified(__('applications.admin.actions.status_updated'));

    expect($application->fresh()->status_id)->toBe($nextStatus->id);

    Livewire::test(ViewApplication::class, ['record' => $application->getRouteKey()])
        ->callAction('moveStatus', ['status_id' => $foreignStatus->id]);

    expect($application->fresh()->status_id)->toBe($nextStatus->id);
});

it('links kanban cards to the tenant-scoped application page and shows compact AI state', function () {
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create();
    $status = Status::factory()->for($company)->create();
    $application = Application::factory()->for($company)->create([
        'job_id' => $job->id,
        'status_id' => $status->id,
        'analysis_status' => ApplicationAnalysisStatus::Pending,
    ]);

    actAsCompany($company);

    $url = ApplicationResource::getUrl('view', ['record' => $application], tenant: $company);

    Livewire::test(JobPipelineKanban::class, ['record' => $job])
        ->assertSee($url, escape: false)
        ->assertSee(__('applications.admin.ai.states.pending.label'));
});

it('provides every admin application status translation', function (string $locale) {
    foreach (ApplicationAnalysisStatus::cases() as $status) {
        expect(Lang::hasForLocale("applications.admin.ai.states.{$status->value}.label", $locale))->toBeTrue()
            ->and(Lang::hasForLocale("applications.admin.ai.states.{$status->value}.title", $locale))->toBeTrue()
            ->and(Lang::hasForLocale("applications.admin.ai.states.{$status->value}.description", $locale))->toBeTrue();
    }

    expect(Lang::hasForLocale('applications.admin.tabs.overview', $locale))->toBeTrue()
        ->and(Lang::hasForLocale('applications.admin.tabs.application', $locale))->toBeTrue()
        ->and(Lang::hasForLocale('applications.admin.tabs.documents', $locale))->toBeTrue()
        ->and(Lang::hasForLocale('applications.admin.tabs.ai_analysis', $locale))->toBeTrue();
})->with(['en', 'pt_BR', 'es']);
