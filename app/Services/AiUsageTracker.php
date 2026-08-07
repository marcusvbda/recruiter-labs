<?php

namespace App\Services;

use App\Enums\AiProvider;
use App\Enums\AiUsageStatus;
use App\Models\AiUsageRecord;
use App\Models\Application;
use App\Models\Job;
use Laravel\Ai\Responses\Data\Usage;

class AiUsageTracker
{
    public function startForJob(
        Job $job,
        ?int $userId,
        string $executionId,
        string $operation,
        string $provider,
        string $model,
        bool $usedOwnKey = false,
    ): AiUsageRecord {
        $attempt = ((int) AiUsageRecord::query()
            ->where('execution_id', $executionId)
            ->max('attempt')) + 1;

        return AiUsageRecord::query()->create([
            'company_id' => $job->company_id,
            'user_id' => $userId,
            'job_id' => $job->getKey(),
            'execution_id' => $executionId,
            'attempt' => $attempt,
            'operation' => $operation,
            'provider' => $usedOwnKey ? AiProvider::Own : AiProvider::Platform,
            'ai_provider' => $provider,
            'model' => $model,
            'status' => AiUsageStatus::Pending,
            'used_own_key' => $usedOwnKey,
        ]);
    }

    public function startForApplication(
        Application $application,
        ?int $userId,
        string $executionId,
        string $operation,
        string $provider,
        string $model,
        bool $usedOwnKey = false,
    ): AiUsageRecord {
        $attempt = ((int) AiUsageRecord::query()
            ->where('execution_id', $executionId)
            ->max('attempt')) + 1;

        return AiUsageRecord::query()->create([
            'company_id' => $application->company_id,
            'user_id' => $userId,
            'application_id' => $application->getKey(),
            'job_id' => $application->job_id,
            'execution_id' => $executionId,
            'attempt' => $attempt,
            'operation' => $operation,
            'provider' => $usedOwnKey ? AiProvider::Own : AiProvider::Platform,
            'ai_provider' => $provider,
            'model' => $model,
            'status' => AiUsageStatus::Pending,
            'used_own_key' => $usedOwnKey,
        ]);
    }

    public function complete(AiUsageRecord $record, Usage $usage, int $durationMilliseconds): void
    {
        $cachedTokens = $usage->cacheReadInputTokens;
        $inputTokens = $usage->promptTokens + $usage->cacheWriteInputTokens + $cachedTokens;
        $outputTokens = $usage->completionTokens;

        $record->update([
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cached_tokens' => $cachedTokens,
            'total_tokens' => $inputTokens + $outputTokens,
            'duration_ms' => $durationMilliseconds,
            'status' => AiUsageStatus::Completed,
        ]);
    }

    public function fail(AiUsageRecord $record, int $durationMilliseconds): void
    {
        $record->update([
            'duration_ms' => $durationMilliseconds,
            'status' => AiUsageStatus::Failed,
        ]);
    }
}
