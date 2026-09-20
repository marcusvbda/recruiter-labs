<?php

use App\Data\AiActivitySnapshot;
use App\Enums\AiActivityState;
use App\Enums\ApplicationAnalysisStatus;
use App\Enums\JobCriteriaProcessingStatus;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Job;
use App\Models\Plan;
use App\Services\AiActivityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * AI Activity may only ever describe state that is really persisted: these
 * assert the indicator stays silent when nothing is running, reports real
 * running work, and collapses a crowd of evaluations into one line instead of
 * one noisy row per candidate.
 */
beforeEach(function (): void {
    Plan::query()->firstOrCreate(
        ['slug' => 'starter'],
        ['name' => 'Starter', 'sort_order' => 1, 'features' => [], 'limits' => []],
    );
});

function aiActivityFor(Company $company): AiActivitySnapshot
{
    return app(AiActivityService::class)->for($company);
}

it('reports up to date when no operation is running', function (): void {
    $company = Company::factory()->create();

    $snapshot = aiActivityFor($company);

    expect($snapshot->state)->toBe(AiActivityState::UpToDate)
        ->and($snapshot->workingCount)->toBe(0)
        ->and($snapshot->working)->toBe([])
        ->and($snapshot->indicatorCount())->toBeNull();
});

it('reports criteria preparation as working', function (): void {
    $company = Company::factory()->create();
    Job::factory()->for($company)->create([
        'criteria_processing_status' => JobCriteriaProcessingStatus::Processing,
    ]);

    $snapshot = aiActivityFor($company);

    expect($snapshot->state)->toBe(AiActivityState::Working)
        ->and($snapshot->workingCount)->toBe(1)
        ->and($snapshot->working[0]->role)->toBe(__('ai_activity.role.criteria_analyst'));
});

it('aggregates a crowd of evaluations into a single line', function (): void {
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create([
        'criteria_processing_status' => JobCriteriaProcessingStatus::Completed,
    ]);

    foreach (range(1, 5) as $ignored) {
        Application::factory()
            ->for($company)
            ->for($job)
            ->for(Candidate::factory()->for($company))
            ->create(['analysis_status' => ApplicationAnalysisStatus::Processing]);
    }

    $snapshot = aiActivityFor($company);

    expect($snapshot->workingCount)->toBe(5)
        ->and($snapshot->working)->toHaveCount(1)
        ->and($snapshot->working[0]->isAggregated())->toBeTrue()
        ->and($snapshot->working[0]->count)->toBe(5);
});

it('reports a waiting evaluation as waiting with a real reason', function (): void {
    $company = Company::factory()->create();
    $job = Job::factory()->for($company)->create();

    Application::factory()
        ->for($company)
        ->for($job)
        ->for(Candidate::factory()->for($company))
        ->create(['analysis_status' => ApplicationAnalysisStatus::AwaitingCriteria]);

    $snapshot = aiActivityFor($company);

    expect($snapshot->state)->toBe(AiActivityState::Waiting)
        ->and($snapshot->waitingCount)->toBe(1)
        ->and($snapshot->waiting[0]->reason)->toBe(__('ai_activity.reason.job_criteria'));
});

it('does not leak another workspace activity', function (): void {
    $company = Company::factory()->create();
    $other = Company::factory()->create();

    Job::factory()->for($other)->create([
        'criteria_processing_status' => JobCriteriaProcessingStatus::Processing,
    ]);

    expect(aiActivityFor($company)->state)->toBe(AiActivityState::UpToDate)
        ->and(aiActivityFor($other)->state)->toBe(AiActivityState::Working);
});
