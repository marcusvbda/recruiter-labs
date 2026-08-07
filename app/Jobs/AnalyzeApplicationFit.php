<?php

namespace App\Jobs;

use App\Actions\ReplaceApplicationFitAnalysis;
use App\Ai\Agents\ScoreApplicationAgainstCriteria;
use App\Enums\AiUsageStatus;
use App\Enums\ApplicationAnalysisStatus;
use App\Enums\ApplicationDocumentType;
use App\Enums\JobCriteriaProcessingStatus;
use App\Enums\Limit;
use App\Models\AiUsageRecord;
use App\Models\Application;
use App\Services\AiCredentialsResolver;
use App\Services\AiUsageTracker;
use App\Services\LimitManager;
use App\Services\ResumeTextExtractor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
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
        AiUsageTracker $usageTracker,
        AiCredentialsResolver $credentialsResolver,
        LimitManager $limitManager,
        ResumeTextExtractor $resumeTextExtractor,
    ): void {
        $application = Application::query()
            ->with(['job.jobCriteria', 'candidate', 'answers', 'documents', 'company'])
            ->find($this->applicationId);

        if ($application === null || $application->analysis_generation !== $this->generation) {
            return;
        }

        if ($application->job->criteria_processing_status !== JobCriteriaProcessingStatus::Completed || $application->job->jobCriteria->isEmpty()) {
            $this->markCurrentGenerationAs(ApplicationAnalysisStatus::AwaitingCriteria);

            return;
        }

        $configuration = $credentialsResolver->resolve($application->company);

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
            $resumeDocument = $application->documents->firstWhere('type', ApplicationDocumentType::Cv);
            $resumeText = $resumeDocument === null ? null : $resumeTextExtractor->extract($resumeDocument);

            $agent = new ScoreApplicationAgainstCriteria($application);
            $response = $agent->prompt(
                $agent->applicationContext($resumeText),
                provider: $runtimeProvider,
                model: $configuration->usesOwnKey ? $configuration->model : null,
            );

            if (! $response instanceof StructuredAgentResponse) {
                throw new UnexpectedValueException('The application fit agent did not return structured output.');
            }

            $scores = $response->toArray()['scores'] ?? null;

            if (! is_array($scores)) {
                throw new UnexpectedValueException('The application fit agent response did not contain scores.');
            }

            $replaceApplicationFitAnalysis->handle($application, $scores, $this->generation);
            $usageTracker->complete($usageRecord, $response->usage, $this->elapsedMilliseconds($startedAt));
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

    private function elapsedMilliseconds(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }
}
