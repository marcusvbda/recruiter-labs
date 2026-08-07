<?php

use App\Enums\ApplicationAnalysisStatus;
use App\Enums\JobCriteriaProcessingStatus;
use App\Jobs\AnalyzeApplicationFit;
use App\Models\Application;
use App\Models\Job;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

it('reschedules a stale application whose job criteria have completed', function () {
    Queue::fake();

    $job = Job::factory()->create();
    $job->jobCriteria()->create([
        'company_id' => $job->company_id,
        'criterion' => 'Laravel expertise',
        'weight' => 9,
        'reason' => 'Core requirement.',
    ]);
    $job->updateQuietly(['criteria_processing_status' => JobCriteriaProcessingStatus::Completed]);
    $application = Application::factory()->for($job->company)->create([
        'job_id' => $job->id,
        'analysis_status' => ApplicationAnalysisStatus::Pending,
        'analysis_generation' => 0,
    ]);

    $this->artisan('applications:backfill-fit-analysis')->assertSuccessful();

    expect($application->refresh()->analysis_status)->toBe(ApplicationAnalysisStatus::Pending)
        ->and($application->analysis_generation)->toBe(1);

    Queue::assertPushed(AnalyzeApplicationFit::class, fn (AnalyzeApplicationFit $queuedJob): bool => $queuedJob->applicationId === $application->id
        && $queuedJob->generation === 1);
});

it('marks a stale application as awaiting criteria when its job criteria are not completed', function () {
    Queue::fake();

    $job = Job::factory()->create();
    $application = Application::factory()->for($job->company)->create([
        'job_id' => $job->id,
        'analysis_status' => ApplicationAnalysisStatus::Pending,
        'analysis_generation' => 0,
    ]);

    $this->artisan('applications:backfill-fit-analysis')->assertSuccessful();

    expect($application->refresh()->analysis_status)->toBe(ApplicationAnalysisStatus::AwaitingCriteria)
        ->and($application->analysis_generation)->toBe(0);

    Queue::assertNothingPushed();
});

it('does not touch an application that is already correctly pending under the new system', function () {
    Queue::fake();

    $job = Job::factory()->create();
    $job->jobCriteria()->create([
        'company_id' => $job->company_id,
        'criterion' => 'Laravel expertise',
        'weight' => 9,
        'reason' => 'Core requirement.',
    ]);
    $job->updateQuietly(['criteria_processing_status' => JobCriteriaProcessingStatus::Completed]);
    $application = Application::factory()->for($job->company)->create([
        'job_id' => $job->id,
        'analysis_status' => ApplicationAnalysisStatus::Pending,
        'analysis_generation' => 1,
    ]);

    $this->artisan('applications:backfill-fit-analysis')->assertSuccessful();

    expect($application->refresh()->analysis_status)->toBe(ApplicationAnalysisStatus::Pending)
        ->and($application->analysis_generation)->toBe(1);

    Queue::assertNothingPushed();
});

it('reports nothing to fix when there are no stale applications', function () {
    $this->artisan('applications:backfill-fit-analysis')
        ->expectsOutputToContain('No stale applications found.')
        ->assertSuccessful();
});
