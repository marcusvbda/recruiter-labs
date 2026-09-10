<?php

namespace App\Actions;

use App\Enums\SourcingSearchStatus;
use App\Jobs\SourceCandidatesForJob;
use App\Models\Company;
use App\Models\Job;
use App\Models\SourcingSearch;
use Illuminate\Support\Facades\DB;

/**
 * Queues a sourcing sweep of the workspace's candidates for one job.
 *
 * The job-level counterpart of {@see ScheduleApplicationFitAnalysis}, and it
 * carries the same criteria gate: {@see Job::hasConfirmedCriteria()} is the only
 * way in, enforced here rather than in the UI, because a suggestion produced
 * against criteria nobody confirmed is exactly the kind of unearned claim the
 * product must not make. There is deliberately no publication or campaign-date
 * gate — sourcing is internal work about people the workspace already knows, and
 * whether the job is open to the public says nothing about it.
 *
 * When the gate is closed the request is simply not accepted: no generation bump,
 * no dispatch, and no status invented to describe it.
 * {@see SourcingSearchStatus} has no persisted `Blocked`/`AwaitingCriteria` case
 * on purpose — "the criteria are not confirmed" is a fact about the job, not a
 * property of a search that never ran, so the workspace derives that state from
 * `hasConfirmedCriteria()` directly, the same way an outdated result is derived
 * from the recorded criteria revision. A stored flag would be one more thing that
 * could disagree with the job.
 *
 * Accepting a request bumps `generation`, which is what lets an already-queued
 * older run recognise that it has been superseded and stop.
 */
class RunSourcingSearch
{
    public function handle(Job $job, ?int $userId = null): void
    {
        $generation = DB::transaction(function () use ($job, $userId): ?int {
            $lockedJob = Job::query()
                ->whereKey($job->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedJob->load('jobCriteria');

            // Criteria are read under the same lock the search row is written
            // under: the revision the run is about to be bound to has to be the
            // one that is confirmed at this instant, not one confirmed a moment
            // before another request changed it.
            if (! $lockedJob->hasConfirmedCriteria() || $lockedJob->jobCriteria->isEmpty()) {
                return null;
            }

            $search = $this->lockedSearchFor($lockedJob);

            // The pool this sweep is about to cover, captured with the criteria
            // revision and under the same lock. Anything that changes the
            // workspace's candidates or their material after this instant —
            // during the run or long after it — advances the company counter and
            // leaves the finished result honestly describing a pool that has
            // since moved on, until the recruiter asks for another sweep.
            $poolRevision = (int) (Company::query()
                ->whereKey($lockedJob->company_id)
                ->value('candidate_pool_revision') ?? 0);

            $search->forceFill([
                'status' => SourcingSearchStatus::Pending,
                'generation' => $search->generation + 1,
                'criteria_generation' => $lockedJob->criteria_generation,
                'candidate_pool_revision' => $poolRevision,
                'requested_by_id' => $userId,
                'started_at' => now(),
                'completed_at' => null,
                // A fresh sweep has reviewed nobody yet. Carrying the previous
                // run's totals would let a run that is still working — or one
                // that stops early — display a summary it has not earned.
                // Existing matches are untouched: they are the recruiter's, and
                // this run will refresh or leave them as it goes.
                'candidates_considered' => null,
                'matches_found' => null,
                'insufficient_count' => null,
            ])->save();

            return $search->generation;
        });

        if ($generation === null) {
            return;
        }

        SourceCandidatesForJob::dispatch($job->getKey(), $userId, $generation)
            ->onConnection((string) config('services.openai.queue_connection', 'database'))
            ->afterCommit();
    }

    /**
     * The job's single search row, locked, created on first request.
     *
     * One row per job (the unique `job_id` key): a rerun refreshes this row
     * rather than adding a competing result, so there is never a question of
     * which search the workspace is looking at.
     */
    private function lockedSearchFor(Job $job): SourcingSearch
    {
        // Created first so the row exists to be locked. `firstOrCreate` absorbs
        // the unique-key violation two simultaneous first requests would
        // otherwise produce and re-reads the winner's row; the lock below then
        // serialises them properly, so a double-click bumps the generation twice
        // instead of failing.
        SourcingSearch::query()->firstOrCreate(
            ['job_id' => $job->getKey()],
            [
                'company_id' => $job->company_id,
                'generation' => 0,
                'status' => SourcingSearchStatus::NotStarted,
            ],
        );

        return SourcingSearch::query()
            ->where('job_id', $job->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }
}
