<?php

use App\Actions\ConfirmJobCriteria;
use App\Actions\ReplaceApplicationFitAnalysis;
use App\Actions\RequireJobCriteriaReview;
use App\Ai\Agents\ScoreApplicationAgainstCriteria;
use App\Enums\ApplicationAnalysisStatus;
use App\Enums\JobCriteriaProcessingStatus;
use App\Jobs\AnalyzeApplicationFit;
use App\Models\AiAgentResponseCache;
use App\Models\AiUsageRecord;
use App\Models\Application;
use App\Models\ApplicationCriterionScore;
use App\Models\ApplicationInterviewBriefItem;
use App\Models\Company;
use App\Models\Job;
use App\Models\JobCriterion;
use App\Models\Plan;
use App\Models\User;
use App\Services\CandidateEvaluationContextSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * An AI evaluation is requested against two revisions at once: the application's
 * analysis generation and one exact confirmed job criteria generation. These
 * tests lock the second one, because criterion IDs alone cannot detect it — a
 * recruiter can rewrite what a criterion means while its database row keeps the
 * same ID, and the response would still map perfectly.
 *
 * @return array{0: Application, 1: array<string, JobCriterion>}
 */
function concurrencyFixture(): array
{
    Plan::query()->firstOrCreate(
        ['slug' => 'starter'],
        ['name' => 'Starter', 'sort_order' => 1, 'features' => [], 'limits' => []],
    );

    $company = Company::factory()->create();
    $job = Job::factory()->withConfirmedCriteria([
        ['criterion' => '3+ years Laravel', 'weight' => 6],
        ['criterion' => 'Led a team of 5+ engineers', 'weight' => 4],
    ])->create(['company_id' => $company->getKey()]);

    $application = Application::factory()->create([
        'company_id' => $company->getKey(),
        'job_id' => $job->getKey(),
        'analysis_status' => ApplicationAnalysisStatus::Processing,
        'analysis_generation' => 1,
    ]);

    return [$application, $job->jobCriteria()->get()->keyBy('criterion')->all()];
}

/**
 * A response the model produced for the criteria as they were, addressing every
 * criterion by the ID it still has.
 *
 * @param  array<string, JobCriterion>  $criteria
 * @return array<int, array<string, mixed>>
 */
function concurrencyScores(array $criteria): array
{
    return array_values(array_map(fn (JobCriterion $criterion): array => [
        'criterion_id' => (int) $criterion->getKey(),
        'score' => 88,
        'reason' => 'The application describes concrete work here.',
        'confidence' => 'high',
        'evidence' => [['source' => 'resume', 'detail' => 'Laravel 11 payments API.']],
    ], $criteria));
}

test('a response produced for one criteria revision is refused once the criteria move on', function (): void {
    [$application, $criteria] = concurrencyFixture();
    $job = $application->job;

    // The revision the request was built from.
    $expectedCriteriaGeneration = (int) $job->criteria_generation;

    // The recruiter rewrites a criterion while the provider request is running.
    // Same row, same ID, materially different meaning and weight.
    $criteria['3+ years Laravel']->forceFill([
        'criterion' => '5+ years Laravel in high-volume systems',
        'weight' => 10,
    ])->save();
    app(RequireJobCriteriaReview::class)->handle($job);

    expect($job->refresh()->criteria_generation)->toBe($expectedCriteriaGeneration + 1)
        ->and($job->criteria_processing_status)->toBe(JobCriteriaProcessingStatus::AwaitingReview);

    $persisted = app(ReplaceApplicationFitAnalysis::class)->handle(
        $application,
        concurrencyScores($criteria),
        [[
            'criterion_id' => (int) $criteria['Led a team of 5+ engineers']->getKey(),
            'priority' => 'high',
            'reason' => 'Team leadership needs validating.',
            'question' => 'What is the largest team you have led?',
        ]],
        1,
        $expectedCriteriaGeneration,
    );

    $application->refresh();

    expect($persisted)->toBeFalse()
        // Waiting for valid criteria again, so confirming the new revision
        // reschedules a fresh evaluation through the existing flow.
        ->and($application->analysis_status)->toBe(ApplicationAnalysisStatus::AwaitingCriteria)
        // Nothing from the stale answer was written.
        ->and($application->analysis_criteria_generation)->toBeNull()
        ->and($application->analysis_score)->toBeNull()
        ->and($application->analysis_coverage)->toBeNull()
        ->and($application->analyzed_at)->toBeNull()
        ->and($application->hasCurrentEvaluation())->toBeFalse()
        ->and(ApplicationCriterionScore::query()->count())->toBe(0)
        ->and(ApplicationInterviewBriefItem::query()->count())->toBe(0);
});

test('a stale response is refused even when the new revision is itself confirmed', function (): void {
    [$application, $criteria] = concurrencyFixture();
    $job = $application->job;

    // The job has been edited and reconfirmed since the request was built, so it
    // has confirmed criteria — just not the revision this answer measured. The
    // columns are written directly to isolate the revision check from the
    // rescheduling ConfirmJobCriteria performs.
    $job->forceFill([
        'criteria_processing_status' => JobCriteriaProcessingStatus::Completed,
        'criteria_generation' => 2,
        'criteria_confirmed_generation' => 2,
        'criteria_confirmed_at' => now(),
    ])->saveQuietly();

    expect($job->refresh()->hasConfirmedCriteria())->toBeTrue();

    $persisted = app(ReplaceApplicationFitAnalysis::class)->handle(
        $application,
        concurrencyScores($criteria),
        [],
        1,
        1,
    );

    expect($persisted)->toBeFalse()
        ->and($application->refresh()->analysis_criteria_generation)->toBeNull()
        ->and(ApplicationCriterionScore::query()->count())->toBe(0);
});

test('a stale response is discarded before its obsolete payload is validated', function (): void {
    [$application] = concurrencyFixture();
    $job = $application->job;

    app(RequireJobCriteriaReview::class)->handle($job);

    $persisted = app(ReplaceApplicationFitAnalysis::class)->handle(
        $application,
        [['criterion_id' => 'not-an-integer']],
        [],
        1,
        1,
    );

    expect($persisted)->toBeFalse()
        ->and($application->refresh()->analysis_status)->toBe(ApplicationAnalysisStatus::AwaitingCriteria)
        ->and(ApplicationCriterionScore::query()->count())->toBe(0);
});

test('a stale response does not overwrite the evaluation already on record', function (): void {
    [$application, $criteria] = concurrencyFixture();
    $job = $application->job;

    // The evaluation the recruiter is currently looking at.
    app(ReplaceApplicationFitAnalysis::class)->handle($application, concurrencyScores($criteria), [[
        'criterion_id' => (int) $criteria['Led a team of 5+ engineers']->getKey(),
        'priority' => 'high',
        'reason' => 'Team leadership needs validating.',
        'question' => 'What is the largest team you have led?',
    ]], 1, 1);

    expect($application->refresh()->hasCurrentEvaluation())->toBeTrue();

    // A reprocess is in flight for generation 2 when the criteria change.
    $application->forceFill(['analysis_generation' => 2, 'analysis_status' => ApplicationAnalysisStatus::Processing])->saveQuietly();
    app(RequireJobCriteriaReview::class)->handle($job);

    $persisted = app(ReplaceApplicationFitAnalysis::class)->handle(
        $application,
        concurrencyScores($criteria),
        [],
        2,
        1,
    );

    $application->refresh();

    expect($persisted)->toBeFalse()
        // The historical evaluation is intact — it simply stops being current.
        ->and((float) $application->analysis_score)->toBe(88.0)
        ->and($application->analysis_criteria_generation)->toBe(1)
        ->and($application->criterionScores()->count())->toBe(2)
        ->and($application->interviewBriefItems()->count())->toBe(1)
        ->and($application->hasCurrentEvaluation())->toBeFalse()
        ->and($application->analysis_status)->toBe(ApplicationAnalysisStatus::AwaitingCriteria);
});

test('a response made stale after reconfirmation schedules the current revision without persisting stale content', function (): void {
    Queue::fake();

    [$application, $criteria] = concurrencyFixture();
    $job = $application->job;
    $recruiter = User::factory()->create();
    $recruiter->companies()->attach($application->company_id);

    ScoreApplicationAgainstCriteria::fake([
        function () use ($job, $recruiter, $criteria): array {
            app(RequireJobCriteriaReview::class)->handle($job);
            app(ConfirmJobCriteria::class)->handle($job->refresh(), $recruiter);

            return ['scores' => concurrencyScores($criteria), 'interview_brief_items' => []];
        },
    ])->preventStrayPrompts();

    app()->call([new AnalyzeApplicationFit($application->getKey(), null, 1), 'handle']);

    expect($job->refresh()->criteria_generation)->toBe(2)
        ->and($job->hasConfirmedCriteria())->toBeTrue()
        ->and($application->refresh()->analysis_status)->toBe(ApplicationAnalysisStatus::Pending)
        ->and($application->analysis_generation)->toBe(2)
        ->and($application->analysis_criteria_generation)->toBeNull()
        ->and(ApplicationCriterionScore::query()->count())->toBe(0);
    Queue::assertPushed(AnalyzeApplicationFit::class, fn (AnalyzeApplicationFit $job): bool => $job->generation === 2);
});

test('a cached response is bound to its criteria revision exactly like a fresh one', function (): void {
    [$application, $criteria] = concurrencyFixture();
    $job = $application->job;

    $application = Application::query()
        ->with(['job.jobCriteria', 'candidate', 'answers', 'documents', 'company'])
        ->findOrFail($application->getKey());

    // The response the provider gave for this exact request, already cached.
    $agent = new ScoreApplicationAgainstCriteria($application);
    $context = $agent->applicationContext(
        app(CandidateEvaluationContextSanitizer::class)->sanitize($application, null),
    );
    AiAgentResponseCache::remember(
        AnalyzeApplicationFit::OPERATION,
        AnalyzeApplicationFit::MODEL,
        applicationFitCacheFingerprint($agent, $context, $job),
        ['scores' => concurrencyScores($criteria), 'interview_brief_items' => []],
    );

    // The criteria change after the request was built and before it is
    // persisted, which is the whole window this guard exists for. Decorating the
    // persistence action is the seam: everything before it — the criteria
    // snapshot, the fingerprint, the cache hit — has already happened.
    app()->bind(ReplaceApplicationFitAnalysis::class, fn (): ReplaceApplicationFitAnalysis => new class($job) extends ReplaceApplicationFitAnalysis
    {
        public function __construct(private readonly Job $job) {}

        public function handle(
            Application $application,
            array $scores,
            array $interviewBriefItems,
            int $expectedGeneration,
            int $expectedCriteriaGeneration,
        ): bool {
            app(RequireJobCriteriaReview::class)->handle($this->job);

            return parent::handle($application, $scores, $interviewBriefItems, $expectedGeneration, $expectedCriteriaGeneration);
        }
    });

    app()->call([new AnalyzeApplicationFit($application->getKey(), null, 1), 'handle']);

    $application->refresh();

    expect($application->analysis_status)->toBe(ApplicationAnalysisStatus::AwaitingCriteria)
        ->and($application->analysis_criteria_generation)->toBeNull()
        ->and($application->analysis_score)->toBeNull()
        ->and(ApplicationCriterionScore::query()->count())->toBe(0)
        // Served from cache, so no provider call and no usage to record.
        ->and(AiUsageRecord::query()->count())->toBe(0);
});

test('a cache entry for a prior confirmed revision is not reused by an identical later revision', function (): void {
    [$application, $criteria] = concurrencyFixture();
    $application = Application::query()
        ->with(['job.jobCriteria', 'candidate', 'answers', 'documents', 'company'])
        ->findOrFail($application->getKey());
    $job = $application->job;

    $agent = new ScoreApplicationAgainstCriteria($application);
    $context = $agent->applicationContext(
        app(CandidateEvaluationContextSanitizer::class)->sanitize($application, null),
    );
    $cachedScores = array_map(
        fn (array $score): array => [...$score, 'score' => 12],
        concurrencyScores($criteria),
    );
    AiAgentResponseCache::remember(
        AnalyzeApplicationFit::OPERATION,
        AnalyzeApplicationFit::MODEL,
        applicationFitCacheFingerprint($agent, $context, $job),
        ['scores' => $cachedScores, 'interview_brief_items' => []],
    );

    app(RequireJobCriteriaReview::class)->handle($job);
    $recruiter = User::factory()->create();
    $recruiter->companies()->attach($application->company_id);
    app(ConfirmJobCriteria::class)->handle($job->refresh(), $recruiter);

    $application->forceFill([
        'analysis_generation' => 2,
        'analysis_status' => ApplicationAnalysisStatus::Processing,
    ])->saveQuietly();

    $freshScores = array_map(
        fn (array $score): array => [...$score, 'score' => 99],
        concurrencyScores($criteria),
    );
    ScoreApplicationAgainstCriteria::fake([
        ['scores' => $freshScores, 'interview_brief_items' => []],
    ])->preventStrayPrompts();

    app()->call([new AnalyzeApplicationFit($application->getKey(), null, 2), 'handle']);

    ScoreApplicationAgainstCriteria::assertPrompted(fn ($prompt): bool => $prompt->prompt === $context);

    expect((float) $application->refresh()->analysis_score)->toBe(99.0)
        ->and($application->analysis_criteria_generation)->toBe(2);
});

function applicationFitCacheFingerprint(ScoreApplicationAgainstCriteria $agent, string $context, Job $job): string
{
    return implode("\n---\n", [
        ScoreApplicationAgainstCriteria::CACHE_SCHEMA_VERSION,
        'job_id:'.$job->getKey(),
        'criteria_generation:'.$job->criteria_generation,
        (string) $agent->instructions(),
        $context,
    ]);
}
