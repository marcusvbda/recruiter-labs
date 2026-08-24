<?php

use App\Actions\ConfirmJobCriteria;
use App\Actions\MoveApplicationToStatus;
use App\Actions\ReplaceJobCriteria;
use App\Actions\RequireJobCriteriaReview;
use App\Actions\ScheduleApplicationFitAnalysis;
use App\Enums\ApplicationAnalysisStatus;
use App\Enums\JobCriteriaProcessingStatus;
use App\Jobs\AnalyzeApplicationFit;
use App\Models\Application;
use App\Models\Company;
use App\Models\Job;
use App\Models\Plan;
use App\Models\Status;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function criteriaCompany(): Company
{
    Plan::query()->firstOrCreate(
        ['slug' => 'starter'],
        ['name' => 'Starter', 'sort_order' => 1, 'features' => [], 'limits' => []],
    );

    return Company::factory()->create();
}

/** A human who belongs to the workspace — what every confirmation requires. */
function criteriaRecruiter(Company $company): User
{
    $recruiter = User::factory()->create();
    $recruiter->companies()->attach($company);

    return $recruiter;
}

test('a finished extraction awaits human review and releases no evaluations', function (): void {
    Queue::fake();

    $company = criteriaCompany();
    $job = Job::factory()->create([
        'company_id' => $company->getKey(),
        'criteria_processing_status' => JobCriteriaProcessingStatus::Processing,
        'criteria_generation' => 1,
    ]);
    $application = Application::factory()->create([
        'company_id' => $company->getKey(),
        'job_id' => $job->getKey(),
        'analysis_status' => ApplicationAnalysisStatus::AwaitingCriteria,
    ]);

    $replaced = app(ReplaceJobCriteria::class)->handle($job, [
        ['criterion' => 'Production Laravel experience', 'weight' => 10, 'reason' => 'Core of the role.'],
    ], [], 1);

    $job->refresh();

    expect($replaced)->toBeTrue()
        ->and($job->criteria_processing_status)->toBe(JobCriteriaProcessingStatus::AwaitingReview)
        ->and($job->hasConfirmedCriteria())->toBeFalse()
        ->and($job->criteria_confirmed_generation)->toBeNull()
        // The criteria exist and are editable, but nothing was evaluated.
        ->and($job->jobCriteria()->count())->toBe(1)
        ->and($application->refresh()->analysis_status)->toBe(ApplicationAnalysisStatus::AwaitingCriteria);

    Queue::assertNotPushed(AnalyzeApplicationFit::class);
});

test('an application cannot be scheduled against unconfirmed criteria', function (): void {
    Queue::fake();

    $company = criteriaCompany();
    $job = Job::factory()->withCriteriaAwaitingReview()->create(['company_id' => $company->getKey()]);
    $application = Application::factory()->create([
        'company_id' => $company->getKey(),
        'job_id' => $job->getKey(),
    ]);

    app(ScheduleApplicationFitAnalysis::class)->handle($application);

    expect($application->refresh()->analysis_status)->toBe(ApplicationAnalysisStatus::AwaitingCriteria)
        ->and($application->analysis_generation)->toBe(0);

    Queue::assertNotPushed(AnalyzeApplicationFit::class);
});

test('confirming the criteria releases the applications waiting for them', function (): void {
    Queue::fake();

    $company = criteriaCompany();
    $recruiter = criteriaRecruiter($company);

    $job = Job::factory()->withCriteriaAwaitingReview()->create(['company_id' => $company->getKey()]);
    $waiting = Application::factory()->create([
        'company_id' => $company->getKey(),
        'job_id' => $job->getKey(),
        'analysis_status' => ApplicationAnalysisStatus::AwaitingCriteria,
    ]);

    $confirmed = app(ConfirmJobCriteria::class)->handle($job, $recruiter);

    $job->refresh();

    expect($confirmed)->toBeTrue()
        ->and($job->criteria_processing_status)->toBe(JobCriteriaProcessingStatus::Completed)
        ->and($job->hasConfirmedCriteria())->toBeTrue()
        ->and($job->criteria_confirmed_generation)->toBe($job->criteria_generation)
        ->and($job->criteria_confirmed_by_id)->toBe((int) $recruiter->getKey())
        ->and($job->criteria_confirmed_at)->not->toBeNull()
        ->and($waiting->refresh()->analysis_status)->toBe(ApplicationAnalysisStatus::Pending);

    Queue::assertPushed(AnalyzeApplicationFit::class, 1);
});

test('confirming a job with no criteria is refused', function (): void {
    $company = criteriaCompany();
    $job = Job::factory()->create([
        'company_id' => $company->getKey(),
        'criteria_processing_status' => JobCriteriaProcessingStatus::AwaitingReview,
        'criteria_generation' => 1,
    ]);

    expect(app(ConfirmJobCriteria::class)->handle($job, criteriaRecruiter($company)))->toBeFalse()
        ->and($job->refresh()->hasConfirmedCriteria())->toBeFalse();
});

test('a recruiter outside the job workspace cannot confirm its criteria', function (): void {
    $jobCompany = criteriaCompany();
    $outsiderCompany = criteriaCompany();
    $outsider = criteriaRecruiter($outsiderCompany);
    $job = Job::factory()->withCriteriaAwaitingReview()->create(['company_id' => $jobCompany->getKey()]);

    expect(fn () => app(ConfirmJobCriteria::class)->handle($job, $outsider))
        ->toThrow(AuthorizationException::class);

    expect($job->refresh()->criteria_processing_status)->toBe(JobCriteriaProcessingStatus::AwaitingReview)
        ->and($job->criteria_confirmed_generation)->toBeNull()
        ->and($job->criteria_confirmed_by_id)->toBeNull();
});

test('a terminal application is not scheduled until it is reopened into an active stage', function (): void {
    Queue::fake();

    $company = criteriaCompany();
    $job = Job::factory()->withConfirmedCriteria()->create(['company_id' => $company->getKey()]);
    $terminalStatus = Status::query()
        ->where('pipeline_id', $job->pipeline_id)
        ->where('is_terminal', true)
        ->firstOrFail();
    $activeStatus = Status::query()
        ->where('pipeline_id', $job->pipeline_id)
        ->where('is_terminal', false)
        ->firstOrFail();
    $application = Application::factory()->create([
        'company_id' => $company->getKey(),
        'job_id' => $job->getKey(),
        'status_id' => $terminalStatus->getKey(),
        'analysis_status' => ApplicationAnalysisStatus::Completed,
        'analysis_generation' => 4,
        'analysis_criteria_generation' => $job->criteria_generation,
        'analysis_score' => 82,
    ]);

    app(ScheduleApplicationFitAnalysis::class)->handle($application);

    expect($application->refresh()->analysis_status)->toBe(ApplicationAnalysisStatus::Completed)
        ->and($application->analysis_generation)->toBe(4)
        ->and((float) $application->analysis_score)->toBe(82.0);
    Queue::assertNotPushed(AnalyzeApplicationFit::class);

    app(MoveApplicationToStatus::class)->handle($application, $activeStatus);
    app(ScheduleApplicationFitAnalysis::class)->handle($application);

    expect($application->refresh()->analysis_status)->toBe(ApplicationAnalysisStatus::Pending)
        ->and($application->analysis_generation)->toBe(5);
    Queue::assertPushed(AnalyzeApplicationFit::class, 1);
});

test('editing confirmed criteria makes the confirmation and the evaluations stale', function (): void {
    Queue::fake();

    $company = criteriaCompany();
    $job = Job::factory()->withConfirmedCriteria()->create(['company_id' => $company->getKey()]);
    $application = Application::factory()->create([
        'company_id' => $company->getKey(),
        'job_id' => $job->getKey(),
        'analysis_status' => ApplicationAnalysisStatus::Completed,
        'analysis_generation' => 1,
        'analysis_criteria_generation' => $job->criteria_generation,
        'analysis_score' => 84,
        'analysis_coverage' => 72,
    ]);

    expect($application->hasCurrentEvaluation())->toBeTrue();

    app(RequireJobCriteriaReview::class)->handle($job);

    $application = $application->fresh(['job']);

    expect($job->refresh()->criteria_processing_status)->toBe(JobCriteriaProcessingStatus::AwaitingReview)
        ->and($job->hasConfirmedCriteria())->toBeFalse()
        // The stored evaluation is untouched; it simply stops being current.
        ->and((float) $application->analysis_score)->toBe(84.0)
        ->and($application->hasCurrentEvaluation())->toBeFalse()
        ->and($application->hasOutdatedEvaluation())->toBeTrue();

    Queue::assertNotPushed(AnalyzeApplicationFit::class);
});

test('reconfirming refreshes in-process stale evaluations but leaves terminal ones alone', function (): void {
    Queue::fake();

    $company = criteriaCompany();
    $job = Job::factory()->withConfirmedCriteria()->create(['company_id' => $company->getKey()]);

    $inProcess = Application::factory()->create([
        'company_id' => $company->getKey(),
        'job_id' => $job->getKey(),
        'analysis_status' => ApplicationAnalysisStatus::Completed,
        'analysis_criteria_generation' => $job->criteria_generation,
    ]);

    $rejectedStatus = Status::query()
        ->where('pipeline_id', $job->pipeline_id)
        ->where('is_terminal', true)
        ->where('is_hired', false)
        ->firstOrFail();
    $terminal = Application::factory()->create([
        'company_id' => $company->getKey(),
        'job_id' => $job->getKey(),
        'status_id' => $rejectedStatus->getKey(),
        'analysis_status' => ApplicationAnalysisStatus::Completed,
        'analysis_criteria_generation' => $job->criteria_generation,
        'analysis_score' => 41,
    ]);

    app(RequireJobCriteriaReview::class)->handle($job);
    app(ConfirmJobCriteria::class)->handle($job->refresh(), criteriaRecruiter($company));

    expect($inProcess->refresh()->analysis_status)->toBe(ApplicationAnalysisStatus::Pending)
        // A closed process keeps its historical evaluation rather than spending
        // AI allowance on a decision nobody will make again.
        ->and($terminal->refresh()->analysis_status)->toBe(ApplicationAnalysisStatus::Completed)
        ->and((float) $terminal->analysis_score)->toBe(41.0);

    Queue::assertPushed(AnalyzeApplicationFit::class, 1);
});

test('an evaluation with no recorded criteria revision is never treated as current', function (): void {
    $company = criteriaCompany();
    $job = Job::factory()->withConfirmedCriteria()->create(['company_id' => $company->getKey()]);
    $application = Application::factory()->create([
        'company_id' => $company->getKey(),
        'job_id' => $job->getKey(),
        'analysis_status' => ApplicationAnalysisStatus::Completed,
        'analysis_criteria_generation' => null,
        'analysis_score' => 77,
    ]);

    expect($application->hasCurrentEvaluation())->toBeFalse()
        ->and($application->hasOutdatedEvaluation())->toBeTrue();
});
