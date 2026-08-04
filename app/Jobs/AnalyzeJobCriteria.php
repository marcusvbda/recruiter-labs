<?php

namespace App\Jobs;

use App\Actions\ReplaceJobCriteria;
use App\Ai\Agents\ExtractJobCriteria;
use App\Enums\AiUsageStatus;
use App\Enums\JobCriteriaProcessingStatus;
use App\Enums\Limit;
use App\Models\AiUsageRecord;
use App\Models\Job;
use App\Services\AiCredentialsResolver;
use App\Services\AiUsageTracker;
use App\Services\LimitManager;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Illuminate\Support\Str;
use Throwable;
use UnexpectedValueException;

class AnalyzeJobCriteria implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 75;

    public int $uniqueFor = 3600;

    public const MODEL = 'gpt-4.1-mini';

    public const OPERATION = 'job_criteria_extraction';

    public const PROVIDER = 'openai';

    public readonly string $executionId;

    public function __construct(
        public readonly int $jobId,
        public readonly ?int $userId,
        public readonly int $generation,
        ?string $executionId = null,
    ) {
        $this->executionId = $executionId ?? (string) Str::uuid();
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 90];
    }

    public function uniqueId(): string
    {
        return $this->jobId.':'.$this->generation;
    }

    /**
     * Execute the job.
     */
    public function handle(
        ReplaceJobCriteria $replaceJobCriteria,
        AiUsageTracker $usageTracker,
        AiCredentialsResolver $credentialsResolver,
        LimitManager $limitManager,
    ): void {
        $job = Job::query()
            ->with(['applicationQuestions', 'acceptedCvTypes', 'coverLetterFileTypes', 'jobCriteria', 'company'])
            ->find($this->jobId);

        if ($job === null || $job->criteria_generation !== $this->generation) {
            return;
        }

        $configuration = $credentialsResolver->resolve($job->company);

        if (! $configuration->usesOwnKey && $limitManager->usage($job->company, Limit::AiAnalyses)->isReached) {
            $this->markCurrentGenerationAsFailed();

            return;
        }

        $startedAt = hrtime(true);
        $usageRecord = $usageTracker->startForJob(
            $job,
            $this->userId,
            $this->executionId,
            self::OPERATION,
            self::PROVIDER,
            $configuration->usesOwnKey ? $configuration->model : self::MODEL,
            $configuration->usesOwnKey,
        );

        $markedAsProcessing = Job::query()
            ->whereKey($job)
            ->where('criteria_generation', $this->generation)
            ->update(['criteria_processing_status' => JobCriteriaProcessingStatus::Processing]);

        if ($markedAsProcessing === 0) {
            $usageTracker->fail($usageRecord, $this->elapsedMilliseconds($startedAt));

            return;
        }

        $runtimeProvider = $credentialsResolver->registerRuntimeProvider($job->company, $configuration);

        try {
            $agent = new ExtractJobCriteria($job);
            $response = $agent->prompt(
                $agent->jobContext(),
                provider: $runtimeProvider,
                model: $configuration->usesOwnKey ? $configuration->model : null,
            );

            if (! $response instanceof StructuredAgentResponse) {
                throw new UnexpectedValueException('The job criteria agent did not return structured output.');
            }

            $criteria = $response->toArray()['criteria'] ?? null;

            if (! is_array($criteria)) {
                throw new UnexpectedValueException('The job criteria agent response did not contain criteria.');
            }

            $replaceJobCriteria->handle($job, $criteria, $this->generation);
            $usageTracker->complete($usageRecord, $response->usage, $this->elapsedMilliseconds($startedAt));
        } catch (Throwable $exception) {
            $usageTracker->fail($usageRecord, $this->elapsedMilliseconds($startedAt));

            throw $exception;
        } finally {
            if ($runtimeProvider !== null) {
                $credentialsResolver->forgetRuntimeProvider($job->company);
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->markCurrentGenerationAsFailed();

        AiUsageRecord::query()
            ->where('execution_id', $this->executionId)
            ->where('status', AiUsageStatus::Pending)
            ->update(['status' => AiUsageStatus::Failed]);
    }

    private function markCurrentGenerationAsFailed(): void
    {
        Job::query()
            ->whereKey($this->jobId)
            ->where('criteria_generation', $this->generation)
            ->update(['criteria_processing_status' => JobCriteriaProcessingStatus::Failed]);
    }

    private function elapsedMilliseconds(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }
}
