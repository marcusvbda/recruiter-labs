<?php

use App\Ai\Agents\ScoreApplicationAgainstCriteria;
use App\Enums\ApplicationQuestionType;
use App\Models\Application;
use App\Models\Job;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    Queue::fake();
});

it('encodes the application context as TOON including job criteria, cover letter, answers, and resume text', function () {
    $job = Job::factory()->create(['name' => 'Senior Laravel Engineer']);
    $job->jobCriteria()->create([
        'company_id' => $job->company_id,
        'criterion' => 'Laravel expertise',
        'weight' => 9,
        'reason' => 'Core requirement.',
    ]);
    $application = Application::factory()->for($job->company)->create([
        'job_id' => $job->id,
        'cover_letter_text' => 'I have shipped several Laravel APIs in production.',
    ]);
    $application->answers()->create([
        'company_id' => $application->company_id,
        'question_snapshot' => 'Years of Laravel experience?',
        'response_type' => ApplicationQuestionType::Number,
        'value_number' => 5,
    ]);

    $context = (new ScoreApplicationAgainstCriteria($application))->applicationContext('Built five Laravel monoliths.');

    expect($context)->toContain('Senior Laravel Engineer')
        ->toContain('Laravel expertise')
        ->toContain('I have shipped several Laravel APIs in production.')
        ->toContain('Years of Laravel experience?')
        ->toContain('Built five Laravel monoliths.');
});

use App\Enums\AiProvider;
use App\Enums\AiUsageStatus;
use App\Services\AiUsageTracker;

it('records both the application and job id when starting usage tracking for an application', function () {
    $job = Job::factory()->create();
    $application = Application::factory()->for($job->company)->create(['job_id' => $job->id]);

    $record = app(AiUsageTracker::class)->startForApplication(
        $application,
        null,
        'exec-1',
        'application_fit_analysis',
        'openai',
        'gpt-4o-mini',
    );

    expect($record->application_id)->toBe($application->id)
        ->and($record->job_id)->toBe($job->id)
        ->and($record->company_id)->toBe($application->company_id)
        ->and($record->provider)->toBe(AiProvider::Platform)
        ->and($record->status)->toBe(AiUsageStatus::Pending);
});

use App\Actions\ScheduleApplicationFitAnalysis;
use App\Enums\ApplicationAnalysisStatus;
use App\Enums\JobCriteriaProcessingStatus;
use App\Jobs\AnalyzeApplicationFit;

it('marks the application as awaiting criteria without dispatching when the job has no completed criteria', function () {
    $job = Job::factory()->create();
    $application = Application::factory()->for($job->company)->create(['job_id' => $job->id]);

    app(ScheduleApplicationFitAnalysis::class)->handle($application);

    expect($application->refresh()->analysis_status)->toBe(ApplicationAnalysisStatus::AwaitingCriteria)
        ->and($application->analysis_generation)->toBe(0);

    Queue::assertNothingPushed();
});

it('queues application analysis and increments the generation when the job has completed criteria', function () {
    $job = Job::factory()->create();
    $job->jobCriteria()->create([
        'company_id' => $job->company_id,
        'criterion' => 'Laravel expertise',
        'weight' => 9,
        'reason' => 'Core requirement.',
    ]);
    $job->updateQuietly(['criteria_processing_status' => JobCriteriaProcessingStatus::Completed]);
    $application = Application::factory()->for($job->company)->create(['job_id' => $job->id]);

    app(ScheduleApplicationFitAnalysis::class)->handle($application);

    expect($application->refresh()->analysis_status)->toBe(ApplicationAnalysisStatus::Pending)
        ->and($application->analysis_generation)->toBe(1);

    Queue::assertPushed(AnalyzeApplicationFit::class, fn (AnalyzeApplicationFit $queuedJob): bool => $queuedJob->applicationId === $application->id
        && $queuedJob->generation === 1
        && $queuedJob->queue === AnalyzeApplicationFit::QUEUE);
});

use App\Actions\ReplaceApplicationFitAnalysis;
use Illuminate\Validation\ValidationException;

it('persists scores, matches weight case-insensitively, and computes the weighted average', function () {
    $job = Job::factory()->create();
    $job->jobCriteria()->createMany([
        ['company_id' => $job->company_id, 'criterion' => 'Laravel expertise', 'weight' => 10, 'reason' => 'r'],
        ['company_id' => $job->company_id, 'criterion' => 'React expertise', 'weight' => 5, 'reason' => 'r'],
    ]);
    $application = Application::factory()->for($job->company)->create(['job_id' => $job->id])->refresh();

    $replaced = app(ReplaceApplicationFitAnalysis::class)->handle($application, [
        ['criterion' => '  laravel expertise  ', 'score' => 80, 'reason' => 'Strong backend skills.', 'confidence' => 'high'],
        ['criterion' => 'react expertise', 'score' => 40, 'reason' => 'Limited frontend exposure.', 'confidence' => 'medium'],
    ], $application->analysis_generation);

    $scores = $application->criterionScores()->orderByDesc('score')->get();

    expect($replaced)->toBeTrue()
        ->and($scores)->toHaveCount(2)
        ->and($scores[0]->weight)->toBe(10)
        ->and($scores[1]->weight)->toBe(5)
        // (80 * 10 + 40 * 5) / 15 = 66.67
        ->and((float) $application->refresh()->analysis_score)->toBe(66.67)
        ->and($application->analysis_status)->toBe(ApplicationAnalysisStatus::Completed)
        ->and($application->analyzed_at)->not->toBeNull();
});

it('falls back to a neutral weight when a returned criterion does not match any job criterion', function () {
    $job = Job::factory()->create();
    $job->jobCriteria()->create(['company_id' => $job->company_id, 'criterion' => 'Laravel expertise', 'weight' => 10, 'reason' => 'r']);
    $application = Application::factory()->for($job->company)->create(['job_id' => $job->id])->refresh();

    app(ReplaceApplicationFitAnalysis::class)->handle($application, [
        ['criterion' => 'Paraphrased differently', 'score' => 60, 'reason' => 'r', 'confidence' => 'low'],
    ], $application->analysis_generation);

    expect($application->criterionScores()->sole()->weight)->toBe(5);
});

it('does not overwrite scores when the generation is stale', function () {
    $job = Job::factory()->create();
    $job->jobCriteria()->create(['company_id' => $job->company_id, 'criterion' => 'Laravel expertise', 'weight' => 10, 'reason' => 'r']);
    $application = Application::factory()->for($job->company)->create(['job_id' => $job->id])->refresh();
    $staleGeneration = $application->analysis_generation;
    $application->update(['analysis_generation' => $staleGeneration + 1]);

    $replaced = app(ReplaceApplicationFitAnalysis::class)->handle($application, [
        ['criterion' => 'Laravel expertise', 'score' => 90, 'reason' => 'r', 'confidence' => 'high'],
    ], $staleGeneration);

    expect($replaced)->toBeFalse()
        ->and($application->criterionScores()->count())->toBe(0);
});

it('rejects invalid structured scores without writing anything', function () {
    $job = Job::factory()->create();
    $application = Application::factory()->for($job->company)->create(['job_id' => $job->id])->refresh();

    expect(fn () => app(ReplaceApplicationFitAnalysis::class)->handle($application, [
        ['criterion' => 'Laravel expertise', 'score' => 150, 'reason' => 'r', 'confidence' => 'high'],
    ], $application->analysis_generation))->toThrow(ValidationException::class);

    expect($application->refresh()->analysis_status)->not->toBe(ApplicationAnalysisStatus::Completed);
});

it('rejects a missing or invalid confidence value without writing anything', function (array $result) {
    $job = Job::factory()->create();
    $job->jobCriteria()->create(['company_id' => $job->company_id, 'criterion' => 'Laravel expertise', 'weight' => 10, 'reason' => 'r']);
    $application = Application::factory()->for($job->company)->create(['job_id' => $job->id])->refresh();

    expect(fn () => app(ReplaceApplicationFitAnalysis::class)->handle($application, [
        $result,
    ], $application->analysis_generation))->toThrow(ValidationException::class);

    expect($application->refresh()->analysis_status)->not->toBe(ApplicationAnalysisStatus::Completed)
        ->and($application->criterionScores()->count())->toBe(0);
})->with([
    'missing confidence' => [['criterion' => 'Laravel expertise', 'score' => 80, 'reason' => 'r']],
    'invalid confidence value' => [['criterion' => 'Laravel expertise', 'score' => 80, 'reason' => 'r', 'confidence' => 'certain']],
]);

use App\Enums\AiCredentialStatus;
use App\Enums\Limit;
use App\Models\AiUsageRecord;
use App\Models\Company;
use App\Models\CompanyAiSetting;
use App\Models\Plan;
use App\Models\User;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredTextResponse;

function completedJob(): Job
{
    $job = Job::factory()->create();
    $job->jobCriteria()->create([
        'company_id' => $job->company_id,
        'criterion' => 'Laravel expertise',
        'weight' => 9,
        'reason' => 'Core requirement.',
    ]);
    $job->updateQuietly(['criteria_processing_status' => JobCriteriaProcessingStatus::Completed]);

    return $job->refresh();
}

it('persists structured scores and complete token usage for the current generation', function () {
    $job = completedJob();
    $responsibleUser = User::factory()->create();
    $responsibleUser->companies()->attach($job->company_id);
    $application = Application::factory()->for($job->company)->create(['job_id' => $job->id])->refresh();

    ScoreApplicationAgainstCriteria::fake([
        new StructuredTextResponse(
            structured: ['scores' => [[
                'criterion' => 'Laravel expertise',
                'score' => 82,
                'reason' => 'Several production Laravel applications delivered.',
                'confidence' => 'high',
            ]]],
            text: '',
            usage: new Usage(promptTokens: 100, completionTokens: 30, cacheWriteInputTokens: 7, cacheReadInputTokens: 20),
            meta: new Meta('openai', AnalyzeApplicationFit::MODEL),
        ),
    ]);

    $queuedJob = new AnalyzeApplicationFit($application->id, $responsibleUser->id, $application->analysis_generation);
    app()->call([$queuedJob, 'handle']);

    $score = $application->criterionScores()->sole();
    $usage = AiUsageRecord::query()->whereBelongsTo($application)->sole();

    expect($application->refresh()->analysis_status)->toBe(ApplicationAnalysisStatus::Completed)
        ->and($score->criterion)->toBe('Laravel expertise')
        ->and($score->score)->toBe(82)
        ->and($usage->user_id)->toBe($responsibleUser->id)
        ->and($usage->job_id)->toBe($job->id)
        ->and($usage->status)->toBe(AiUsageStatus::Completed);

    ScoreApplicationAgainstCriteria::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->model === 'gpt-4o-mini'
        && $prompt->contains('Laravel expertise'));
});

it('sets pending quota without calling the agent when the platform quota is reached', function () {
    $plan = Plan::query()->where('slug', 'starter')->sole();
    $plan->update(['limits' => [...$plan->limits, Limit::AiAnalyses->value => 0]]);
    $company = Company::factory()->create(['plan_id' => $plan->id]);
    $job = Job::factory()->for($company)->create();
    $job->jobCriteria()->create(['company_id' => $company->id, 'criterion' => 'Laravel expertise', 'weight' => 9, 'reason' => 'r']);
    $job->updateQuietly(['criteria_processing_status' => JobCriteriaProcessingStatus::Completed]);
    $application = Application::factory()->for($company)->create(['job_id' => $job->id])->refresh();

    ScoreApplicationAgainstCriteria::fake()->preventStrayPrompts();

    $queuedJob = new AnalyzeApplicationFit($application->id, null, $application->analysis_generation);
    app()->call([$queuedJob, 'handle']);

    ScoreApplicationAgainstCriteria::assertNeverPrompted();
    expect($application->refresh()->analysis_status)->toBe(ApplicationAnalysisStatus::PendingQuota)
        ->and(AiUsageRecord::query()->whereBelongsTo($application)->count())->toBe(0);
});

it('does not enforce the platform quota when the company uses its own key', function () {
    $plan = Plan::query()->where('slug', 'pro')->sole();
    $plan->update(['limits' => [...$plan->limits, Limit::AiAnalyses->value => 0]]);
    $company = Company::factory()->create(['plan_id' => $plan->id]);
    CompanyAiSetting::factory()->for($company)->create([
        'provider' => AiProvider::Own,
        'openai_api_key' => 'sk-company-secret',
        'model' => 'gpt-4o',
        'credential_status' => AiCredentialStatus::Active,
    ]);
    $job = Job::factory()->for($company)->create();
    $job->jobCriteria()->create(['company_id' => $company->id, 'criterion' => 'Laravel expertise', 'weight' => 9, 'reason' => 'r']);
    $job->updateQuietly(['criteria_processing_status' => JobCriteriaProcessingStatus::Completed]);
    $application = Application::factory()->for($company)->create(['job_id' => $job->id])->refresh();

    ScoreApplicationAgainstCriteria::fake([
        new StructuredTextResponse(
            structured: ['scores' => [['criterion' => 'Laravel expertise', 'score' => 70, 'reason' => 'r', 'confidence' => 'medium']]],
            text: '',
            usage: new Usage(promptTokens: 50, completionTokens: 15),
            meta: new Meta('openai', 'gpt-4o'),
        ),
    ]);

    $queuedJob = new AnalyzeApplicationFit($application->id, null, $application->analysis_generation);
    app()->call([$queuedJob, 'handle']);

    $usage = AiUsageRecord::query()->whereBelongsTo($application)->sole();

    expect($application->refresh()->analysis_status)->toBe(ApplicationAnalysisStatus::Completed)
        ->and($usage->used_own_key)->toBeTrue();

    expect(config('ai.providers.byok:'.$company->id))->toBeNull();
});

it('marks the current generation and its usage as failed when the agent call fails', function () {
    $job = completedJob();
    $application = Application::factory()->for($job->company)->create(['job_id' => $job->id])->refresh();

    ScoreApplicationAgainstCriteria::fake(fn (): never => throw new RuntimeException('Provider unavailable'));

    $queuedJob = new AnalyzeApplicationFit($application->id, null, $application->analysis_generation);

    expect(fn () => app()->call([$queuedJob, 'handle']))
        ->toThrow(RuntimeException::class, 'Provider unavailable');

    expect($application->refresh()->analysis_status)->toBe(ApplicationAnalysisStatus::Processing)
        ->and(AiUsageRecord::query()->whereBelongsTo($application)->sole()->status)->toBe(AiUsageStatus::Failed);

    $queuedJob->failed(new RuntimeException('Provider unavailable'));

    expect($application->refresh()->analysis_status)->toBe(ApplicationAnalysisStatus::Failed);
});

it('does not run or overwrite scores for a stale queued generation', function () {
    $job = completedJob();
    $application = Application::factory()->for($job->company)->create(['job_id' => $job->id])->refresh();
    $staleGeneration = $application->analysis_generation;

    app(ScheduleApplicationFitAnalysis::class)->handle($application);
    ScoreApplicationAgainstCriteria::fake()->preventStrayPrompts();

    $queuedJob = new AnalyzeApplicationFit($application->id, null, $staleGeneration);
    app()->call([$queuedJob, 'handle']);

    ScoreApplicationAgainstCriteria::assertNeverPrompted();
    expect(AiUsageRecord::query()->whereBelongsTo($application)->count())->toBe(0)
        ->and($application->refresh()->analysis_generation)->toBe($staleGeneration + 1)
        ->and($application->analysis_status)->toBe(ApplicationAnalysisStatus::Pending);
});

use App\Actions\ReplaceJobCriteria;

it('retroactively schedules applications left awaiting criteria once the job criteria finish generating', function () {
    $job = Job::factory()->create()->refresh();
    $application = Application::factory()->for($job->company)->create(['job_id' => $job->id]);
    app(ScheduleApplicationFitAnalysis::class)->handle($application);
    expect($application->refresh()->analysis_status)->toBe(ApplicationAnalysisStatus::AwaitingCriteria);

    app(ReplaceJobCriteria::class)->handle($job, [[
        'criterion' => 'Laravel expertise',
        'weight' => 9,
        'reason' => 'Core requirement.',
    ]], $job->criteria_generation);

    expect($application->refresh()->analysis_status)->toBe(ApplicationAnalysisStatus::Pending)
        ->and($application->analysis_generation)->toBe(1);

    Queue::assertPushed(AnalyzeApplicationFit::class, fn (AnalyzeApplicationFit $queuedJob): bool => $queuedJob->applicationId === $application->id);
});
