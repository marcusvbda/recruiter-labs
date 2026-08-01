<?php

use App\Filament\Resources\Jobs\JobResource;
use App\Filament\Resources\Jobs\Pages\JobPipeline;
use App\Filament\Resources\Jobs\Widgets\JobPipelineKanban;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Job;
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

it('renders the job pipeline as a kanban without the list view', function () {
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create();

    actAsCompany($company);

    $this->get(JobResource::getUrl('pipeline', ['record' => $job], tenant: $company))
        ->assertSuccessful()
        ->assertSeeLivewire(JobPipeline::class)
        ->assertSeeLivewire(JobPipelineKanban::class)
        ->assertDontSee(__('applications.pipeline.view_list'));
});
