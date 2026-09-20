<?php

use App\Enums\AiExecutionOrigin;
use App\Enums\AiUsageStatus;
use App\Enums\ApplicationAnalysisStatus;
use App\Enums\CompanyRole;
use App\Filament\Pages\Dashboard;
use App\Jobs\AnalyzeApplicationFit;
use App\Jobs\AnalyzeJobCriteria;
use App\Models\AiUsageRecord;
use App\Models\Application;
use App\Models\Company;
use App\Models\Job;
use App\Models\Plan;
use App\Models\User;
use App\Services\RecruiterProductivityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * The productivity figures are a promise about what the product did for the
 * recruiter, so these tests are mostly about what must *not* be counted:
 * results that are already stale, work a person asked for themselves, and
 * another workspace's numbers.
 */
beforeEach(function (): void {
    Plan::query()->firstOrCreate(
        ['slug' => 'starter'],
        ['name' => 'Starter', 'sort_order' => 1, 'features' => [], 'limits' => []],
    );
});

/** An application with a finished evaluation and the usage record behind it. */
function evaluatedApplication(
    Company $company,
    Job $job,
    AiExecutionOrigin $origin = AiExecutionOrigin::Automatic,
    ?int $criteriaGeneration = null,
    ?string $analyzedAt = null,
): Application {
    $application = Application::factory()->create([
        'company_id' => $company->getKey(),
        'job_id' => $job->getKey(),
        'analysis_status' => ApplicationAnalysisStatus::Completed,
        'analysis_criteria_generation' => $criteriaGeneration ?? $job->criteria_generation,
        'analyzed_at' => $analyzedAt ?? now(),
    ]);

    AiUsageRecord::factory()->create([
        'company_id' => $company->getKey(),
        'application_id' => $application->getKey(),
        'job_id' => $job->getKey(),
        'operation' => AnalyzeApplicationFit::OPERATION,
        'origin' => $origin,
        'status' => AiUsageStatus::Completed,
    ]);

    return $application;
}

function productivityWorkspace(): array
{
    $company = Company::factory()->create();
    $owner = User::factory()->create();
    $company->users()->attach($owner, ['role' => CompanyRole::Owner->value]);
    $job = Job::factory()->withConfirmedCriteria()->create(['company_id' => $company->getKey()]);

    return [$company, $owner, $job];
}

test('only automatic, still-current evaluations completed in the period are counted', function (): void {
    [$company, , $job] = productivityWorkspace();

    // Counted: automatic, measured against the job's current criteria, today.
    evaluatedApplication($company, $job);

    // Not counted: the recruiter asked for this one themselves.
    evaluatedApplication($company, $job, AiExecutionOrigin::UserRequested);

    // Not counted: the criteria revision it measured is superseded, so the
    // result has to be redone — no review time was saved.
    evaluatedApplication($company, $job, criteriaGeneration: $job->criteria_generation - 1);

    // Not counted: finished before this week started.
    evaluatedApplication($company, $job, analyzedAt: now()->startOfWeek()->subDay()->toDateTimeString());

    $snapshot = app(RecruiterProductivityService::class)->for($company);

    expect($snapshot->applicationsEvaluated)->toBe(1);
});

test('automatic AI work completed adds the criteria preparations behind the evaluations', function (): void {
    [$company, , $job] = productivityWorkspace();

    AiUsageRecord::factory()->create([
        'company_id' => $company->getKey(),
        'job_id' => $job->getKey(),
        'operation' => AnalyzeJobCriteria::OPERATION,
        'origin' => AiExecutionOrigin::Automatic,
        'status' => AiUsageStatus::Completed,
    ]);

    evaluatedApplication($company, $job);

    $snapshot = app(RecruiterProductivityService::class)->for($company);

    expect($snapshot->applicationsEvaluated)->toBe(1)
        ->and($snapshot->criteriaPrepared)->toBe(1)
        ->and($snapshot->aiWorkCompleted())->toBe(2);
});

test('another workspace never contributes to these figures', function (): void {
    [$company, , $job] = productivityWorkspace();
    [$other, , $otherJob] = productivityWorkspace();

    evaluatedApplication($company, $job);
    evaluatedApplication($other, $otherJob);
    evaluatedApplication($other, $otherJob);

    expect(app(RecruiterProductivityService::class)->for($company)->applicationsEvaluated)->toBe(1)
        ->and(app(RecruiterProductivityService::class)->for($other)->applicationsEvaluated)->toBe(2);
});

test('no baseline means no time-saved number at all', function (): void {
    [$company, , $job] = productivityWorkspace();

    evaluatedApplication($company, $job);

    $snapshot = app(RecruiterProductivityService::class)->for($company);

    expect($snapshot->manualReviewMinutes)->toBeNull()
        ->and($snapshot->estimatedMinutesSaved())->toBeNull();
});

test('a configured baseline multiplies only the automatic evaluations of the period', function (): void {
    [$company, , $job] = productivityWorkspace();
    $company->update(['manual_review_minutes_per_application' => 25]);

    evaluatedApplication($company, $job);
    evaluatedApplication($company, $job);
    evaluatedApplication($company, $job, AiExecutionOrigin::UserRequested);

    $snapshot = app(RecruiterProductivityService::class)->for($company->refresh());

    expect($snapshot->applicationsEvaluated)->toBe(2)
        ->and($snapshot->estimatedMinutesSaved())->toBe(50);
});

test('the Overview renders the AI work and productivity regions', function (): void {
    [$company, $owner, $job] = productivityWorkspace();
    evaluatedApplication($company, $job);

    $this->actingAs($owner)
        ->get(Dashboard::getUrl(tenant: $company))
        ->assertSuccessful()
        ->assertSee(__('dashboard.ai_activity.heading'))
        ->assertSee(__('dashboard.productivity.heading'))
        // No baseline: the measured count is there, the estimate is not.
        ->assertSee(trans_choice('dashboard.productivity.applications_evaluated', 1))
        ->assertDontSee(__('dashboard.productivity.time_saved', ['duration' => '']));
});
