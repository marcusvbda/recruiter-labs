<?php

namespace App\Actions;

use App\Enums\AiExecutionOrigin;
use App\Enums\JobCriteriaProcessingStatus;
use App\Jobs\AnalyzeJobCriteria;
use App\Models\Job;
use App\Services\AiActivityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ScheduleJobCriteriaExtraction
{
    public function handle(
        Job $job,
        ?int $userId = null,
        AiExecutionOrigin $origin = AiExecutionOrigin::UserRequested,
        ?string $trigger = 'criteria_regeneration_requested',
    ): void {
        $generation = DB::transaction(function () use ($job): int {
            $lockedJob = Job::query()->whereKey($job->getKey())->lockForUpdate()->firstOrFail();

            $lockedJob->forceFill([
                'criteria_processing_status' => JobCriteriaProcessingStatus::Pending,
                'criteria_generation' => $lockedJob->criteria_generation + 1,
            ])->saveQuietly();

            return $lockedJob->criteria_generation;
        });

        $this->broadcastActivity($job);

        $this->dispatch($job, $userId, $generation, $origin, $trigger);
    }

    /**
     * Schedules the first suggestion at most once for a newly created job.
     * Eligibility and transition to Pending share a lock, so equivalent model
     * lifecycle signals cannot each advance the generation.
     */
    public function handleInitial(Job $job, string $trigger = 'job_created'): bool
    {
        $generation = DB::transaction(function () use ($job): ?int {
            $lockedJob = Job::query()->whereKey($job->getKey())->lockForUpdate()->firstOrFail();

            // Existing or copied criteria always belong to the recruiter who
            // created or edited them. Initial automation must never replace
            // them, and a non-fresh processing state is already meaningful.
            if ($lockedJob->criteria_processing_status !== JobCriteriaProcessingStatus::NotStarted
                || $lockedJob->criteria_confirmed_generation !== null
                || $lockedJob->criteria_confirmed_at !== null
                || $lockedJob->criteria_confirmed_by_id !== null
                || $lockedJob->jobCriteria()->exists()
                || ! $this->hasSubstantiveRoleDescription($lockedJob)) {
                return null;
            }

            $lockedJob->forceFill([
                'criteria_processing_status' => JobCriteriaProcessingStatus::Pending,
                'criteria_generation' => $lockedJob->criteria_generation + 1,
            ])->saveQuietly();

            return $lockedJob->criteria_generation;
        });

        if ($generation === null) {
            return false;
        }

        $this->broadcastActivity($job);

        $this->dispatch($job, null, $generation, AiExecutionOrigin::Automatic, $trigger);

        return true;
    }

    /**
     * Queued criteria work must be as visible as running criteria work. The
     * transitions above use `saveQuietly()`, which bypasses the model events
     * that would otherwise broadcast, so the AI Activity channel is notified
     * explicitly — after the surrounding transaction commits, so the indicator
     * never reads a status a rollback would undo.
     */
    private function broadcastActivity(Job $job): void
    {
        DB::afterCommit(fn () => AiActivityService::broadcastForJob($job->getKey()));
    }

    private function hasSubstantiveRoleDescription(Job $job): bool
    {
        // A title identifies a job but does not describe one. Forty normalized
        // characters is enough to require role context while remaining well
        // below a typical job-description paragraph.
        return mb_strlen(Str::squish(strip_tags((string) $job->description))) >= 40;
    }

    private function dispatch(
        Job $job,
        ?int $userId,
        int $generation,
        AiExecutionOrigin $origin,
        ?string $trigger,
    ): void {
        AnalyzeJobCriteria::dispatch($job->getKey(), $userId, $generation, origin: $origin, trigger: $trigger)
            ->onConnection((string) config('services.openai.queue_connection', 'database'))
            ->afterCommit();
    }
}
