<?php

namespace App\Jobs;

use App\Actions\ReplaceApplicationFitAnalysis;
use App\Actions\ScheduleApplicationFitAnalysis;
use App\Ai\Agents\ScoreApplicationAgainstCriteria;
use App\Enums\AiUsageStatus;
use App\Enums\ApplicationAnalysisStatus;
use App\Enums\ApplicationDocumentType;
use App\Enums\Limit;
use App\Models\AiAgentResponseCache;
use App\Models\AiUsageRecord;
use App\Models\Application;
use App\Models\Job;
use App\Services\AiCredentialsResolver;
use App\Services\AiUsageTracker;
use App\Services\CandidateEvaluationContextSanitizer;
use App\Services\LimitManager;
use App\Services\ResumeTextExtractor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;
use UnexpectedValueException;

class AnalyzeApplicationFit implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 75;

    public int $uniqueFor = 3600;

    public const MODEL = 'gpt-4o-mini';

    public const OPERATION = 'application_fit_analysis';

    public const PROVIDER = 'openai';

    public const QUEUE = 'ai-application-analysis';

    public readonly string $executionId;

    public function __construct(
        public readonly int $applicationId,
        public readonly ?int $userId,
        public readonly int $generation,
        ?string $executionId = null,
    ) {
        $this->executionId = $executionId ?? (string) Str::uuid();
        $this->queue = self::QUEUE;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 90];
    }

    public function uniqueId(): string
    {
        return $this->applicationId.':'.$this->generation;
    }

    public function handle(
        ReplaceApplicationFitAnalysis $replaceApplicationFitAnalysis,
        ScheduleApplicationFitAnalysis $scheduleApplicationFitAnalysis,
        AiUsageTracker $usageTracker,
        AiCredentialsResolver $credentialsResolver,
        LimitManager $limitManager,
        ResumeTextExtractor $resumeTextExtractor,
        CandidateEvaluationContextSanitizer $contextSanitizer,
    ): void {
        $application = Application::query()
            ->with(['candidate', 'answers', 'documents', 'company'])
            ->find($this->applicationId);

        if ($application === null || $application->analysis_generation !== $this->generation) {
            return;
        }

        $job = $this->captureConfirmedCriteriaSnapshot($application);

        if ($job === null) {
            $this->markCurrentGenerationAs(ApplicationAnalysisStatus::AwaitingCriteria);

            return;
        }

        // This hydrated relation is deliberately the locked snapshot above. The
        // context builder must not reload it after the lock has been released:
        // the exact confirmed revision and criterion set are one unit of work.
        $application->setRelation('job', $job);
        $expectedCriteriaGeneration = (int) $job->criteria_generation;

        $configuration = $credentialsResolver->resolve($application->company);
        $model = $configuration->usesOwnKey ? $configuration->model : self::MODEL;

        $resumeDocument = $application->documents->firstWhere('type', ApplicationDocumentType::Cv);
        $resumeText = $resumeDocument === null ? null : $resumeTextExtractor->extract($resumeDocument);

        // Direct candidate identifiers are removed before anything reaches the
        // provider, and the fingerprint below is built from that reduced
        // context — never from the identity-bearing original.
        $agent = new ScoreApplicationAgainstCriteria($application);
        $context = $agent->applicationContext($contextSanitizer->sanitize($application, $resumeText));
        $fingerprint = implode("\n---\n", [
            ScoreApplicationAgainstCriteria::CACHE_SCHEMA_VERSION,
            'job_id:'.$job->getKey(),
            'criteria_generation:'.$expectedCriteriaGeneration,
            (string) $agent->instructions(),
            $context,
        ]);

        $cached = AiAgentResponseCache::lookup(self::OPERATION, $model, $fingerprint);

        if ($cached !== null) {
            $scores = $cached['scores'] ?? null;
            $interviewBriefItems = $cached['interview_brief_items'] ?? null;

            if (! is_array($scores) || ! is_array($interviewBriefItems)) {
                throw new UnexpectedValueException('The cached application fit response did not contain the expected structured output.');
            }

            $markedAsProcessing = Application::query()
                ->whereKey($application)
                ->where('analysis_generation', $this->generation)
                ->update(['analysis_status' => ApplicationAnalysisStatus::Processing]);

            if ($markedAsProcessing === 0) {
                return;
            }

            // A cached answer is bound to its criteria revision exactly like a
            // fresh one: the fingerprint proves the request matched, the
            // revision check proves the job has not moved on since.
            $persisted = $replaceApplicationFitAnalysis->handle(
                $application,
                $scores,
                $interviewBriefItems,
                $this->generation,
                $expectedCriteriaGeneration,
            );

            if (! $persisted) {
                $this->scheduleCurrentCriteriaIfStillAwaiting(
                    $scheduleApplicationFitAnalysis,
                    $application,
                );
            }

            return;
        }

        if (! $configuration->usesOwnKey && $limitManager->usage($application->company, Limit::AiAnalyses)->isReached) {
            $this->markCurrentGenerationAs(ApplicationAnalysisStatus::PendingQuota);

            return;
        }

        $startedAt = hrtime(true);
        $usageRecord = $usageTracker->startForApplication(
            $application,
            $this->userId,
            $this->executionId,
            self::OPERATION,
            self::PROVIDER,
            $configuration->usesOwnKey ? $configuration->model : self::MODEL,
            $configuration->usesOwnKey,
        );

        $markedAsProcessing = Application::query()
            ->whereKey($application)
            ->where('analysis_generation', $this->generation)
            ->update(['analysis_status' => ApplicationAnalysisStatus::Processing]);

        if ($markedAsProcessing === 0) {
            $usageTracker->fail($usageRecord, $this->elapsedMilliseconds($startedAt));

            return;
        }

        $runtimeProvider = $credentialsResolver->registerRuntimeProvider($application->company, $configuration);

        try {
            $response = $agent->prompt(
                $context,
                provider: $runtimeProvider,
                model: $configuration->usesOwnKey ? $configuration->model : null,
            );

            if (! $response instanceof StructuredAgentResponse) {
                throw new UnexpectedValueException('The application fit agent did not return structured output.');
            }

            $scores = $response->toArray()['scores'] ?? null;
            $interviewBriefItems = $response->toArray()['interview_brief_items'] ?? null;

            if (! is_array($scores) || ! is_array($interviewBriefItems)) {
                throw new UnexpectedValueException('The application fit agent response did not contain the expected structured output.');
            }

            // A stale revision makes the answer unusable, not the call a
            // failure: the provider really did the work, so the usage record
            // stays honest and the response is still cached under its own
            // fingerprint. What it must not become is a current evaluation.
            $persisted = $replaceApplicationFitAnalysis->handle(
                $application,
                $scores,
                $interviewBriefItems,
                $this->generation,
                $expectedCriteriaGeneration,
            );
            $usageTracker->complete($usageRecord, $response->usage, $this->elapsedMilliseconds($startedAt));
            AiAgentResponseCache::remember(self::OPERATION, $model, $fingerprint, $response->toArray());

            if (! $persisted) {
                $this->scheduleCurrentCriteriaIfStillAwaiting(
                    $scheduleApplicationFitAnalysis,
                    $application,
                );
            }
        } catch (Throwable $exception) {
            $usageTracker->fail($usageRecord, $this->elapsedMilliseconds($startedAt));

            throw $exception;
        } finally {
            if ($runtimeProvider !== null) {
                $credentialsResolver->forgetRuntimeProvider($application->company);
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->markCurrentGenerationAs(ApplicationAnalysisStatus::Failed);

        AiUsageRecord::query()
            ->where('execution_id', $this->executionId)
            ->where('status', AiUsageStatus::Pending)
            ->update(['status' => AiUsageStatus::Failed]);
    }

    private function markCurrentGenerationAs(ApplicationAnalysisStatus $status): void
    {
        Application::query()
            ->whereKey($this->applicationId)
            ->where('analysis_generation', $this->generation)
            ->update(['analysis_status' => $status]);
    }

    /**
     * Lock only long enough to bind one confirmed criteria revision to the
     * prompt. Provider, cache and sanitizer work all happen after this
     * transaction commits; persistence verifies this same revision again.
     */
    private function captureConfirmedCriteriaSnapshot(Application $application): ?Job
    {
        return DB::transaction(function () use ($application): ?Job {
            $job = Job::query()
                ->whereKey($application->job_id)
                ->lockForUpdate()
                ->first();

            if ($job === null) {
                return null;
            }

            $job->load('jobCriteria');

            return $job->hasConfirmedCriteria() && $job->jobCriteria->isNotEmpty()
                ? $job
                : null;
        });
    }

    /**
     * A response can become stale after the next revision has already been
     * confirmed. Confirmation intentionally skips an in-flight application, so
     * schedule the new revision only after persistence has released its locks.
     */
    private function scheduleCurrentCriteriaIfStillAwaiting(
        ScheduleApplicationFitAnalysis $scheduleApplicationFitAnalysis,
        Application $application,
    ): void {
        $application->refresh();

        if ($application->analysis_generation !== $this->generation
            || $application->analysis_status !== ApplicationAnalysisStatus::AwaitingCriteria) {
            return;
        }

        $scheduleApplicationFitAnalysis->handle($application, $this->userId, $this->generation);
    }

    private function elapsedMilliseconds(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }
}
