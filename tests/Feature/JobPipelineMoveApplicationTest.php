<?php

use App\Filament\Resources\Jobs\Pages\JobPipeline;
use App\Models\Application;
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
// `JobPipeline::moveApplication()` is the single most security-sensitive
// piece introduced by this checkpoint: it is a public Livewire method
// reachable with attacker-supplied ids from the client (the Kanban drag
// handler), so it must re-validate server-side that both the application and
// the destination status belong to the bound job/company before persisting.

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

    Livewire::test(JobPipeline::class, ['record' => $job->getRouteKey()])
        ->call('moveApplication', $application->id, $destinationStatus->id);

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

    Livewire::test(JobPipeline::class, ['record' => $job->getRouteKey()])
        ->call('moveApplication', $application->id, $foreignStatus->id);

    expect($application->fresh()->status_id)->toBe($originalStatus->id)
        ->and($application->fresh()->status_id)->not->toBe($foreignStatus->id);
});

it('rejects moving an application that does not belong to the bound job', function () {
    $company = Company::factory()->create();

    $job = Job::factory()->for($company)->create();
    $otherJob = Job::factory()->for($company)->create();

    $originalStatus = Status::factory()->for($company)->create(['order' => 0]);
    $destinationStatus = Status::factory()->for($company)->create(['order' => 1]);

    // Belongs to `$otherJob`, not the job the pipeline page is bound to.
    $foreignApplication = Application::factory()->for($company)->create([
        'job_id' => $otherJob->id,
        'status_id' => $originalStatus->id,
    ]);

    actAsCompany($company);

    Livewire::test(JobPipeline::class, ['record' => $job->getRouteKey()])
        ->call('moveApplication', $foreignApplication->id, $destinationStatus->id);

    expect($foreignApplication->fresh()->status_id)->toBe($originalStatus->id);
});

it('rejects moving an application belonging to a different company even when the status also belongs to that other company', function () {
    // Belt-and-braces adversarial case: both the application and the
    // destination status are foreign to the tenant currently acting, so
    // the job-ownership check alone (via `job_id`) must still catch it.
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

    Livewire::test(JobPipeline::class, ['record' => $job->getRouteKey()])
        ->call('moveApplication', $foreignApplication->id, $foreignStatus->id);

    expect($foreignApplication->fresh()->status_id)->toBe($foreignStatus->id);
});
