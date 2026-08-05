<?php

use App\Enums\JobCriteriaProcessingStatus;
use App\Filament\Resources\Jobs\Pages\EditJob;
use App\Jobs\AnalyzeJobCriteria;
use App\Models\Company;
use App\Models\Job;
use Database\Seeders\CvFileTypeSeeder;
use Database\Seeders\PlanSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

function aiCriteriaAction(string $name): TestAction
{
    return TestAction::make($name)->schemaComponent(true, 'form');
}

// Note: the `actAsCompany()` helper used below is declared once, globally, in
// tests/Pest.php and shared across tenant-isolation test files.

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    $this->seed(CvFileTypeSeeder::class);
    Queue::fake();
});

it('starts the first AI analysis from the not started state', function () {
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create();
    $job->updateQuietly(['criteria_processing_status' => JobCriteriaProcessingStatus::NotStarted]);
    $generationBeforeStart = $job->refresh()->criteria_generation;
    actAsCompany($company);
    Queue::fake();

    Livewire::test(EditJob::class, ['record' => $job->getRouteKey()])
        ->set('activeJobEditTab', 'ai-criteria')
        ->assertSee(__('jobs.criteria.start_action'))
        ->assertDontSee(__('jobs.criteria.retry_action'))
        ->assertDontSee(__('jobs.criteria.rerun_action'))
        ->assertActionVisible(aiCriteriaAction('startAiCriteriaAnalysis'))
        ->callAction(aiCriteriaAction('startAiCriteriaAnalysis'));

    $expectedGeneration = $generationBeforeStart + 1;

    expect($job->fresh()->criteria_processing_status)->toBe(JobCriteriaProcessingStatus::Pending)
        ->and($job->fresh()->criteria_generation)->toBe($expectedGeneration);

    Queue::assertPushed(AnalyzeJobCriteria::class, fn (AnalyzeJobCriteria $queuedJob): bool => $queuedJob->jobId === $job->id
        && $queuedJob->generation === $expectedGeneration
        && $queuedJob->queue === AnalyzeJobCriteria::QUEUE);
});

it('retries a failed AI analysis', function () {
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create();
    $job->updateQuietly(['criteria_processing_status' => JobCriteriaProcessingStatus::Failed]);
    actAsCompany($company);
    Queue::fake();

    Livewire::test(EditJob::class, ['record' => $job->getRouteKey()])
        ->set('activeJobEditTab', 'ai-criteria')
        ->assertDontSee(__('jobs.criteria.start_action'))
        ->assertSee(__('jobs.criteria.retry_action'))
        ->assertDontSee(__('jobs.criteria.rerun_action'))
        ->assertActionVisible(aiCriteriaAction('retryAiCriteriaAnalysis'))
        ->callAction(aiCriteriaAction('retryAiCriteriaAnalysis'));

    expect($job->fresh()->criteria_processing_status)->toBe(JobCriteriaProcessingStatus::Pending);
    Queue::assertPushed(AnalyzeJobCriteria::class);
});

it('re-runs a completed AI analysis', function () {
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create();
    $job->updateQuietly(['criteria_processing_status' => JobCriteriaProcessingStatus::Completed]);
    actAsCompany($company);
    Queue::fake();

    Livewire::test(EditJob::class, ['record' => $job->getRouteKey()])
        ->set('activeJobEditTab', 'ai-criteria')
        ->assertDontSee(__('jobs.criteria.start_action'))
        ->assertDontSee(__('jobs.criteria.retry_action'))
        ->assertSee(__('jobs.criteria.rerun_action'))
        ->assertActionVisible(aiCriteriaAction('rerunAiCriteriaAnalysis'))
        ->callAction(aiCriteriaAction('rerunAiCriteriaAnalysis'));

    expect($job->fresh()->criteria_processing_status)->toBe(JobCriteriaProcessingStatus::Pending);
    Queue::assertPushed(AnalyzeJobCriteria::class);
});

it('hides every manual trigger action while an analysis is pending or processing', function (JobCriteriaProcessingStatus $status) {
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create();
    $job->updateQuietly(['criteria_processing_status' => $status]);
    actAsCompany($company);
    Queue::fake();

    Livewire::test(EditJob::class, ['record' => $job->getRouteKey()])
        ->set('activeJobEditTab', 'ai-criteria')
        ->assertDontSee(__('jobs.criteria.start_action'))
        ->assertDontSee(__('jobs.criteria.retry_action'))
        ->assertDontSee(__('jobs.criteria.rerun_action'))
        ->assertDontSee(__('jobs.criteria.section_title'));

    Queue::assertNothingPushed();
})->with([
    'pending' => [JobCriteriaProcessingStatus::Pending],
    'processing' => [JobCriteriaProcessingStatus::Processing],
]);
