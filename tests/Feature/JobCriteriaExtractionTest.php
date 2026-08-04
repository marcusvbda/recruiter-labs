<?php

use App\Actions\InvalidateJobCriteriaExtraction;
use App\Actions\ReplaceJobCriteria;
use App\Actions\ScheduleJobCriteriaExtraction;
use App\Ai\Agents\ExtractJobCriteria;
use App\Enums\AiCredentialStatus;
use App\Enums\AiProvider;
use App\Enums\AiUsageStatus;
use App\Enums\ApplicationQuestionType;
use App\Enums\JobCriteriaProcessingStatus;
use App\Enums\Limit;
use App\Jobs\AnalyzeJobCriteria;
use App\Models\AiUsageRecord;
use App\Models\Company;
use App\Models\CompanyAiSetting;
use App\Models\Job;
use App\Models\JobApplicationQuestion;
use App\Models\JobCriterion;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredTextResponse;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    Queue::fake();
});

it('does not queue criteria extraction automatically when a job is created or updated', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $job = Job::factory()->create();

    expect($job->refresh()->criteria_processing_status)->toBe(JobCriteriaProcessingStatus::NotStarted)
        ->and($job->criteria_generation)->toBe(0);

    $job->update(['description' => 'Updated role description']);

    expect($job->refresh()->criteria_generation)->toBe(0)
        ->and($job->criteria_processing_status)->toBe(JobCriteriaProcessingStatus::NotStarted);

    Queue::assertNothingPushed();
});

it('does not queue criteria extraction automatically when a custom question is created updated or deleted', function () {
    $job = Job::factory()->create();

    $question = JobApplicationQuestion::factory()->create([
        'company_id' => $job->company_id,
        'job_id' => $job->id,
        'question' => 'How many years of Laravel experience do you have?',
        'response_type' => ApplicationQuestionType::Number,
        'required' => true,
    ]);

    $question->update(['required' => false]);
    $question->delete();

    expect($job->refresh()->criteria_generation)->toBe(0);
    Queue::assertNothingPushed();
});

it('does not queue extraction when criteria are edited manually', function () {
    $job = Job::factory()->create();
    Queue::fake();

    $criterion = JobCriterion::query()->create([
        'company_id' => $job->company_id,
        'job_id' => $job->id,
        'criterion' => 'Laravel experience',
        'weight' => 8,
        'reason' => 'The role requires Laravel expertise.',
    ]);

    $criterion->update(['weight' => 10]);

    Queue::assertNothingPushed();
});

it('invalidates an in-flight generation before manual criteria are persisted', function () {
    $job = Job::factory()->create()->refresh();
    $generation = $job->criteria_generation;
    $criterion = JobCriterion::query()->create([
        'company_id' => $job->company_id,
        'job_id' => $job->id,
        'criterion' => 'Human-reviewed criterion',
        'weight' => 9,
        'reason' => 'This value was reviewed by a recruiter.',
    ]);
    Queue::fake();

    app(InvalidateJobCriteriaExtraction::class)->handle($job);

    $replaced = app(ReplaceJobCriteria::class)->handle($job, [[
        'criterion' => 'Stale AI criterion',
        'weight' => 2,
        'reason' => 'This response completed too late.',
    ]], $generation);

    expect($replaced)->toBeFalse()
        ->and($job->refresh()->criteria_generation)->toBe($generation + 1)
        ->and($job->criteria_processing_status)->toBe(JobCriteriaProcessingStatus::Completed)
        ->and($criterion->refresh()->criterion)->toBe('Human-reviewed criterion');

    Queue::assertNothingPushed();
});

it('persists structured criteria and complete token usage for the current generation', function () {
    $job = Job::factory()->create([
        'name' => 'Senior Laravel Engineer',
        'description' => 'Build a multi-tenant recruiting platform.',
    ])->refresh();
    $responsibleUser = User::factory()->create();
    $responsibleUser->companies()->attach($job->company_id);

    JobApplicationQuestion::withoutEvents(fn () => JobApplicationQuestion::factory()->create([
        'company_id' => $job->company_id,
        'job_id' => $job->id,
        'question' => 'Describe your Laravel experience.',
        'response_type' => ApplicationQuestionType::Textarea,
    ]));

    ExtractJobCriteria::fake([
        new StructuredTextResponse(
            structured: [
                'criteria' => [[
                    'criterion' => 'Laravel expertise',
                    'weight' => 9,
                    'reason' => 'The role requires building a Laravel application.',
                ]],
            ],
            text: '',
            usage: new Usage(promptTokens: 100, completionTokens: 30, cacheWriteInputTokens: 7, cacheReadInputTokens: 20),
            meta: new Meta('openai', AnalyzeJobCriteria::MODEL),
        ),
    ]);

    $queuedJob = new AnalyzeJobCriteria($job->id, $responsibleUser->id, $job->criteria_generation);
    app()->call([$queuedJob, 'handle']);

    $criterion = $job->jobCriteria()->sole();
    $usage = AiUsageRecord::query()->whereBelongsTo($job)->sole();

    expect($job->refresh()->criteria_processing_status)->toBe(JobCriteriaProcessingStatus::Completed)
        ->and($criterion->criterion)->toBe('Laravel expertise')
        ->and($criterion->weight)->toBe(9)
        ->and($criterion->reason)->toBe('The role requires building a Laravel application.')
        ->and($criterion->company_id)->toBe($job->company_id)
        ->and($usage->user_id)->toBe($responsibleUser->id)
        ->and($usage->company_id)->toBe($job->company_id)
        ->and($usage->job_id)->toBe($job->id)
        ->and($usage->provider->value)->toBe('platform')
        ->and($usage->ai_provider)->toBe('openai')
        ->and($usage->model)->toBe('gpt-4.1-mini')
        ->and($usage->input_tokens)->toBe(127)
        ->and($usage->output_tokens)->toBe(30)
        ->and($usage->cached_tokens)->toBe(20)
        ->and($usage->total_tokens)->toBe(157)
        ->and($usage->status)->toBe(AiUsageStatus::Completed);

    ExtractJobCriteria::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->model === 'gpt-4.1-mini'
        && $prompt->provider()->name() === 'openai'
        && $prompt->contains('Senior Laravel Engineer')
        && $prompt->contains('Describe your Laravel experience.'));
});

it('marks the current generation and its usage as failed when extraction fails', function () {
    $job = Job::factory()->create()->refresh();

    ExtractJobCriteria::fake(fn (): never => throw new RuntimeException('Provider unavailable'));

    $queuedJob = new AnalyzeJobCriteria($job->id, null, $job->criteria_generation);

    $exception = new RuntimeException('Provider unavailable');

    expect(fn () => app()->call([$queuedJob, 'handle']))
        ->toThrow(RuntimeException::class, 'Provider unavailable');

    expect($job->refresh()->criteria_processing_status)->toBe(JobCriteriaProcessingStatus::Processing)
        ->and(AiUsageRecord::query()->whereBelongsTo($job)->sole()->status)->toBe(AiUsageStatus::Failed);

    $queuedJob->failed($exception);

    expect($job->refresh()->criteria_processing_status)->toBe(JobCriteriaProcessingStatus::Failed);
});

it('does not run or overwrite criteria for a stale queued generation', function () {
    $job = Job::factory()->create()->refresh();
    $staleGeneration = $job->criteria_generation;

    app(ScheduleJobCriteriaExtraction::class)->handle($job);
    ExtractJobCriteria::fake()->preventStrayPrompts();

    $queuedJob = new AnalyzeJobCriteria($job->id, null, $staleGeneration);
    app()->call([$queuedJob, 'handle']);

    ExtractJobCriteria::assertNeverPrompted();
    expect(AiUsageRecord::query()->whereBelongsTo($job)->count())->toBe(0)
        ->and($job->refresh()->criteria_generation)->toBe($staleGeneration + 1)
        ->and($job->criteria_processing_status)->toBe(JobCriteriaProcessingStatus::Pending);
});

it('rejects invalid structured criteria without replacing existing data', function (array $invalidCriteria) {
    $job = Job::factory()->create()->refresh();
    $statusBeforeAttempt = $job->criteria_processing_status;
    $existingCriterion = $job->jobCriteria()->create([
        'company_id' => $job->company_id,
        'criterion' => 'Existing criterion',
        'weight' => 6,
        'reason' => 'This criterion was already reviewed.',
    ]);

    expect(fn () => app(ReplaceJobCriteria::class)->handle($job, $invalidCriteria, $job->criteria_generation))
        ->toThrow(ValidationException::class);

    expect($job->jobCriteria()->sole()->is($existingCriterion))->toBeTrue()
        ->and($job->refresh()->criteria_processing_status)->toBe($statusBeforeAttempt);
})->with([
    'empty list' => [[]],
    'out-of-range weight' => [[[
        'criterion' => 'Laravel expertise',
        'weight' => 11,
        'reason' => 'Laravel is required.',
    ]]],
    'missing reason' => [[[
        'criterion' => 'Laravel expertise',
        'weight' => 8,
    ]]],
    'unexpected tenant field' => [[[
        'criterion' => 'Laravel expertise',
        'weight' => 8,
        'reason' => 'Laravel is required.',
        'company_id' => 999,
    ]]],
]);

it('keeps existing criteria when invalid agent output exhausts its retries', function () {
    $job = Job::factory()->create()->refresh();
    $existingCriterion = $job->jobCriteria()->create([
        'company_id' => $job->company_id,
        'criterion' => 'Existing criterion',
        'weight' => 6,
        'reason' => 'This criterion was already reviewed.',
    ]);

    ExtractJobCriteria::fake([[
        'criteria' => [[
            'criterion' => 'Invalid criterion',
            'weight' => 11,
            'reason' => 'The weight is outside the accepted range.',
        ]],
    ]]);

    $queuedJob = new AnalyzeJobCriteria($job->id, null, $job->criteria_generation);

    expect(fn () => app()->call([$queuedJob, 'handle']))
        ->toThrow(ValidationException::class);

    expect($job->jobCriteria()->sole()->is($existingCriterion))->toBeTrue()
        ->and($job->refresh()->criteria_processing_status)->toBe(JobCriteriaProcessingStatus::Processing)
        ->and(AiUsageRecord::query()->whereBelongsTo($job)->sole()->status)->toBe(AiUsageStatus::Failed);

    $queuedJob->failed(new RuntimeException('The invalid response exhausted its retries.'));

    expect($job->refresh()->criteria_processing_status)->toBe(JobCriteriaProcessingStatus::Failed)
        ->and($job->jobCriteria()->sole()->is($existingCriterion))->toBeTrue();
});

it('does not overwrite criteria when its generation becomes stale during the agent call', function () {
    $job = Job::factory()->create()->refresh();
    $generation = $job->criteria_generation;
    $existingCriterion = $job->jobCriteria()->create([
        'company_id' => $job->company_id,
        'criterion' => 'Manually reviewed criterion',
        'weight' => 10,
        'reason' => 'A recruiter approved this criterion.',
    ]);

    ExtractJobCriteria::fake(function () use ($job): StructuredTextResponse {
        app(ScheduleJobCriteriaExtraction::class)->handle($job);

        return new StructuredTextResponse(
            structured: [
                'criteria' => [[
                    'criterion' => 'Stale generated criterion',
                    'weight' => 5,
                    'reason' => 'This response belongs to an older job generation.',
                ]],
            ],
            text: '',
            usage: new Usage(promptTokens: 40, completionTokens: 10),
            meta: new Meta('openai', AnalyzeJobCriteria::MODEL),
        );
    });

    $queuedJob = new AnalyzeJobCriteria($job->id, null, $generation);
    app()->call([$queuedJob, 'handle']);

    expect($job->jobCriteria()->sole()->is($existingCriterion))->toBeTrue()
        ->and($job->refresh()->criteria_generation)->toBe($generation + 1)
        ->and($job->criteria_processing_status)->toBe(JobCriteriaProcessingStatus::Pending)
        ->and(AiUsageRecord::query()->whereBelongsTo($job)->sole()->status)->toBe(AiUsageStatus::Completed);
});

it('can retry the same queued execution after a transient provider failure', function () {
    $job = Job::factory()->create()->refresh();
    $attempts = 0;

    ExtractJobCriteria::fake(function () use (&$attempts): array {
        $attempts++;

        if ($attempts === 1) {
            throw new RuntimeException('Transient provider failure');
        }

        return [
            'criteria' => [[
                'criterion' => 'Laravel expertise',
                'weight' => 8,
                'reason' => 'The role requires Laravel expertise.',
            ]],
        ];
    });

    $queuedJob = new AnalyzeJobCriteria($job->id, null, $job->criteria_generation);

    expect(fn () => app()->call([$queuedJob, 'handle']))
        ->toThrow(RuntimeException::class, 'Transient provider failure');

    app()->call([$queuedJob, 'handle']);

    expect($attempts)->toBe(2)
        ->and($job->refresh()->criteria_processing_status)->toBe(JobCriteriaProcessingStatus::Completed)
        ->and($job->jobCriteria()->sole()->criterion)->toBe('Laravel expertise')
        ->and(AiUsageRecord::query()->whereBelongsTo($job)->count())->toBe(2)
        ->and(AiUsageRecord::query()->whereBelongsTo($job)->orderBy('attempt')->pluck('status')->all())->toBe([
            AiUsageStatus::Failed,
            AiUsageStatus::Completed,
        ]);
});

it('fails only the matching pending execution after a hard timeout', function () {
    $job = Job::factory()->create()->refresh();
    $queuedJob = new AnalyzeJobCriteria($job->id, null, $job->criteria_generation);

    $timedOutUsage = AiUsageRecord::factory()->create([
        'company_id' => $job->company_id,
        'job_id' => $job->id,
        'execution_id' => $queuedJob->executionId,
        'operation' => AnalyzeJobCriteria::OPERATION,
        'status' => AiUsageStatus::Pending,
    ]);
    $retriedTimedOutUsage = AiUsageRecord::factory()->create([
        'company_id' => $job->company_id,
        'job_id' => $job->id,
        'execution_id' => $queuedJob->executionId,
        'attempt' => 2,
        'operation' => AnalyzeJobCriteria::OPERATION,
        'status' => AiUsageStatus::Pending,
    ]);
    $differentUsage = AiUsageRecord::factory()->create([
        'company_id' => $job->company_id,
        'job_id' => $job->id,
        'operation' => AnalyzeJobCriteria::OPERATION,
        'status' => AiUsageStatus::Pending,
    ]);

    $queuedJob->failed(new RuntimeException('The worker timed out.'));

    expect($job->refresh()->criteria_processing_status)->toBe(JobCriteriaProcessingStatus::Failed)
        ->and($timedOutUsage->refresh()->status)->toBe(AiUsageStatus::Failed)
        ->and($retriedTimedOutUsage->refresh()->status)->toBe(AiUsageStatus::Failed)
        ->and($differentUsage->refresh()->status)->toBe(AiUsageStatus::Pending);
});

it('does not fail a newer pending usage record when a stale generation reaches its failed hook', function () {
    $job = Job::factory()->create()->refresh();
    $staleGeneration = $job->criteria_generation;

    app(ScheduleJobCriteriaExtraction::class)->handle($job);

    $currentUsage = AiUsageRecord::factory()->create([
        'company_id' => $job->company_id,
        'job_id' => $job->id,
        'operation' => AnalyzeJobCriteria::OPERATION,
        'status' => AiUsageStatus::Pending,
    ]);

    (new AnalyzeJobCriteria($job->id, null, $staleGeneration))->failed(new RuntimeException('Stale failure'));

    expect($job->refresh()->criteria_processing_status)->toBe(JobCriteriaProcessingStatus::Pending)
        ->and($currentUsage->refresh()->status)->toBe(AiUsageStatus::Pending);
});

it('does not enforce the platform quota and prompts the company key when the company uses its own key', function () {
    $plan = Plan::query()->where('slug', 'pro')->sole();
    $plan->update(['limits' => [...$plan->limits, Limit::AiAnalyses->value => 0]]);
    $company = Company::factory()->create(['plan_id' => $plan->id]);
    CompanyAiSetting::factory()->for($company)->create([
        'provider' => AiProvider::Own,
        'openai_api_key' => 'sk-company-secret',
        'model' => 'gpt-4o',
        'credential_status' => AiCredentialStatus::Active,
    ]);
    $job = Job::factory()->for($company)->create()->refresh();

    ExtractJobCriteria::fake([
        new StructuredTextResponse(
            structured: [
                'criteria' => [[
                    'criterion' => 'Laravel expertise',
                    'weight' => 9,
                    'reason' => 'The role requires Laravel expertise.',
                ]],
            ],
            text: '',
            usage: new Usage(promptTokens: 50, completionTokens: 15),
            meta: new Meta('openai', 'gpt-4o'),
        ),
    ]);

    $queuedJob = new AnalyzeJobCriteria($job->id, null, $job->criteria_generation);
    app()->call([$queuedJob, 'handle']);

    $usage = AiUsageRecord::query()->whereBelongsTo($job)->sole();

    expect($job->refresh()->criteria_processing_status)->toBe(JobCriteriaProcessingStatus::Completed)
        ->and($usage->used_own_key)->toBeTrue()
        ->and($usage->provider)->toBe(AiProvider::Own)
        ->and($usage->model)->toBe('gpt-4o');

    ExtractJobCriteria::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->model === 'gpt-4o'
        && $prompt->provider()->name() === 'byok:'.$company->id);

    expect(config('ai.providers.byok:'.$company->id))->toBeNull();
});

it('fails the current generation without calling the agent when the platform quota is reached', function () {
    $plan = Plan::query()->where('slug', 'starter')->sole();
    $plan->update(['limits' => [...$plan->limits, Limit::AiAnalyses->value => 0]]);
    $company = Company::factory()->create(['plan_id' => $plan->id]);
    $job = Job::factory()->for($company)->create()->refresh();

    ExtractJobCriteria::fake()->preventStrayPrompts();

    $queuedJob = new AnalyzeJobCriteria($job->id, null, $job->criteria_generation);
    app()->call([$queuedJob, 'handle']);

    ExtractJobCriteria::assertNeverPrompted();

    expect($job->refresh()->criteria_processing_status)->toBe(JobCriteriaProcessingStatus::Failed)
        ->and(AiUsageRecord::query()->whereBelongsTo($job)->count())->toBe(0);
});
