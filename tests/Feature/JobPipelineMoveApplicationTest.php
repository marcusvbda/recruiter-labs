<?php

use App\Enums\ApplicationSource;
use App\Filament\Resources\Applications\ApplicationResource;
use App\Filament\Resources\Jobs\JobResource;
use App\Filament\Resources\Jobs\Pages\ViewJob;
use App\Filament\Resources\Jobs\Widgets\JobPipelineKanban;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Job;
use App\Models\Referral;
use App\Models\Status;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

// Note: the `actAsCompany()` helper used below is declared once, globally, in
// tests/Pest.php and shared across tenant-isolation test files.
//
uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

it('moves an application belonging to the bound job to a status belonging to the same company', function () {
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create();
    $originalStatus = Status::factory()->for($company)->create(['order' => 0]);
    $destinationStatus = Status::factory()->for($company)->create(['order' => 1]);
    $application = Application::factory()->for($company)->create([
        'job_id' => $job->id,
        'status_id' => $originalStatus->id,
    ]);

    actAsCompany($company);

    Livewire::test(JobPipelineKanban::class, ['record' => $job])
        ->call('moveRecord', $application->id, (string) $destinationStatus->id);

    expect($application->fresh()->status_id)->toBe($destinationStatus->id);
});

it('rejects moving an application to a status belonging to a different company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $job = Job::factory()->for($companyA)->create();
    $originalStatus = Status::factory()->for($companyA)->create(['order' => 0]);
    $application = Application::factory()->for($companyA)->create([
        'job_id' => $job->id,
        'status_id' => $originalStatus->id,
    ]);

    $foreignStatus = Status::factory()->for($companyB)->create();

    actAsCompany($companyA);

    Livewire::test(JobPipelineKanban::class, ['record' => $job])
        ->call('moveRecord', $application->id, (string) $foreignStatus->id);

    expect($application->fresh()->status_id)->toBe($originalStatus->id)
        ->and($application->fresh()->status_id)->not->toBe($foreignStatus->id);
});

it('rejects moving an application that does not belong to the bound job', function () {
    $company = Company::factory()->create();

    $job = Job::factory()->for($company)->create();
    $otherJob = Job::factory()->for($company)->create();

    $originalStatus = Status::factory()->for($company)->create(['order' => 0]);
    $destinationStatus = Status::factory()->for($company)->create(['order' => 1]);

    $foreignApplication = Application::factory()->for($company)->create([
        'job_id' => $otherJob->id,
        'status_id' => $originalStatus->id,
    ]);

    actAsCompany($company);

    Livewire::test(JobPipelineKanban::class, ['record' => $job])
        ->call('moveRecord', $foreignApplication->id, (string) $destinationStatus->id);

    expect($foreignApplication->fresh()->status_id)->toBe($originalStatus->id);
});

it('rejects moving an application belonging to a different company even when the status also belongs to that other company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $job = Job::factory()->for($companyA)->create();

    $otherJob = Job::factory()->for($companyB)->create();
    $foreignStatus = Status::factory()->for($companyB)->create();
    $foreignApplication = Application::factory()->for($companyB)->create([
        'job_id' => $otherJob->id,
        'status_id' => $foreignStatus->id,
    ]);

    actAsCompany($companyA);

    Livewire::test(JobPipelineKanban::class, ['record' => $job])
        ->call('moveRecord', $foreignApplication->id, (string) $foreignStatus->id);

    expect($foreignApplication->fresh()->status_id)->toBe($foreignStatus->id);
});

it('shows only candidates linked to the bound job on the kanban board', function () {
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create();
    $otherJob = Job::factory()->for($company)->create();
    $status = Status::factory()->for($company)->create();
    $includedCandidate = Candidate::factory()->for($company)->create(['name' => 'Included Candidate']);
    $excludedCandidate = Candidate::factory()->for($company)->create(['name' => 'Excluded Candidate']);

    Application::factory()->for($company)->create([
        'job_id' => $job->id,
        'candidate_id' => $includedCandidate->id,
        'status_id' => $status->id,
    ]);
    Application::factory()->for($company)->create([
        'job_id' => $otherJob->id,
        'candidate_id' => $excludedCandidate->id,
        'status_id' => $status->id,
    ]);

    actAsCompany($company);

    Livewire::test(JobPipelineKanban::class, ['record' => $job])
        ->assertSee($includedCandidate->name)
        ->assertDontSee($excludedCandidate->name);
});

it('shows a compact application summary and an explicit details link on each pipeline card', function () {
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create();
    $status = Status::factory()->for($company)->create();
    $candidate = Candidate::factory()->for($company)->create();
    $application = Application::factory()->for($company)->create([
        'job_id' => $job->id,
        'candidate_id' => $candidate->id,
        'status_id' => $status->id,
    ]);

    $application->answers()->create([
        'company_id' => $company->id,
        'question_snapshot' => 'Why do you want this role?',
        'response_type' => 'text',
        'value_text' => 'A thoughtful answer.',
    ]);
    $application->documents()->create([
        'company_id' => $company->id,
        'type' => 'cv',
        'disk' => 'local',
        'path' => 'applications/test-cv.pdf',
        'original_name' => 'test-cv.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'size' => 100,
        'checksum' => hash('sha256', 'test'),
        'uploaded_at' => now(),
    ]);

    actAsCompany($company);

    Livewire::test(JobPipelineKanban::class, ['record' => $job])
        ->assertSee(__('applications.pipeline.kanban.applied_on', [
            'date' => $application->created_at->translatedFormat('M j'),
        ]))
        ->assertSee(trans_choice('applications.pipeline.kanban.answers', 1, ['count' => 1]))
        ->assertSee(trans_choice('applications.pipeline.kanban.documents', 1, ['count' => 1]))
        ->assertSee(__('applications.pipeline.kanban.view_details'))
        ->assertSeeHtml('href="'.ApplicationResource::getUrl('view', [
            'record' => $application,
        ], tenant: $company).'"');
});

it('visually identifies only referral applications on pipeline cards', function () {
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create();
    $status = Status::factory()->for($company)->create();
    $referral = Referral::factory()->for($company)->for($job)->create();
    $referralApplication = Application::factory()->for($company)->create([
        'job_id' => $job->id,
        'status_id' => $status->id,
        'referral_id' => $referral->id,
        'source' => ApplicationSource::Referral,
    ]);
    $directApplication = Application::factory()->for($company)->create([
        'job_id' => $job->id,
        'status_id' => $status->id,
        'source' => ApplicationSource::Direct,
    ]);

    actAsCompany($company);

    $html = Livewire::test(JobPipelineKanban::class, ['record' => $job])
        ->assertSee(__('applications.pipeline.kanban.referral'))
        ->html();

    expect($html)
        ->toMatch('/data-record-id="'.$referralApplication->getKey().'"\s+data-referral="true"/')
        ->toMatch('/data-record-id="'.$directApplication->getKey().'"\s+data-referral="false"/');
});

it('tints each pipeline column with its validated status color', function () {
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create();
    Status::factory()->for($company)->create([
        'color' => '#8B5CF6',
        'order' => 1,
    ]);
    Status::factory()->for($company)->create([
        'color' => 'red; display: none',
        'order' => 2,
    ]);

    actAsCompany($company);

    Livewire::test(JobPipelineKanban::class, ['record' => $job])
        ->assertSee('--rl-status-color: #8b5cf6', escape: false)
        ->assertSee('--rl-status-color: #94a3b8', escape: false)
        ->assertDontSee('red; display: none', escape: false);
});

it('renders the job pipeline as a kanban inside the job view', function () {
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create();

    actAsCompany($company);

    $this->get(JobResource::getUrl('view', ['record' => $job], tenant: $company))
        ->assertSuccessful()
        ->assertSeeLivewire(ViewJob::class)
        ->assertSeeLivewire(JobPipelineKanban::class)
        ->assertDontSee(__('applications.pipeline.view_list'));
});

it('translates the pipeline interface without translating database status names', function () {
    app()->setLocale('pt_BR');

    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create();
    $firstStatus = Status::factory()->for($company)->create([
        'name' => 'Applied from database',
        'order' => 0,
    ]);
    $secondStatus = Status::factory()->for($company)->create([
        'name' => 'Screening from database',
        'order' => 1,
    ]);

    actAsCompany($company);

    Livewire::test(JobPipelineKanban::class, ['record' => $job])
        ->assertSee(__('applications.pipeline.kanban.heading'))
        ->assertSee(__('applications.pipeline.kanban.search_placeholder'))
        ->assertSee(__('applications.pipeline.kanban.all_statuses'))
        ->assertSee(__('applications.pipeline.kanban.can_move_to'))
        ->assertSee(__('applications.pipeline.kanban.no_matching_applications'))
        ->assertSee($firstStatus->name)
        ->assertSee($secondStatus->name)
        ->assertDontSee('Workflow board')
        ->assertDontSee('Search records...')
        ->assertDontSee('All states')
        ->assertDontSee('Can move to')
        ->assertDontSee('No matching records');
});
