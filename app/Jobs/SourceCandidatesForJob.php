<?php

namespace App\Jobs;

use App\Actions\ReplaceSourcingMatchAnalysis;
use App\Ai\Agents\ScoreCandidateForSourcing;
use App\Enums\AiUsageStatus;
use App\Enums\Limit;
use App\Enums\SourcingSearchStatus;
use App\Models\AiAgentResponseCache;
use App\Models\AiUsageRecord;
use App\Models\Job;
use App\Models\SourcingSearch;
use App\Services\AiCredentialsResolver;
use App\Services\AiUsageTracker;
use App\Services\CandidateSourcingContextSanitizer;
use App\Services\CandidateSourcingEligibilityService;
use App\Services\LimitManager;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;
use UnexpectedValueException;

/**
 * Sweeps a workspace's candidates against one job's confirmed criteria.
 *
 * Structurally the same as {@see AnalyzeApplicationFit} — generation check,
 * criteria re-validation, quota/BYOK resolution, response cache, usage record,
 * deterministic persistence — with one difference that shapes everything else:
 * this is a *run over many candidates*, so it can stop halfway.
 *
 * Three rules follow from that, and none of them may be relaxed for convenience:
 *
 * - **Only a complete pass may end in {@see SourcingSearchStatus::Completed}.**
 *   Running out of AI allowance ends the run in
 *   {@see SourcingSearchStatus::PendingQuota} and a provider failure ends it in
 *   {@see SourcingSearchStatus::Failed}, each carrying the counts actually
 *   reached. Matches already written stay visible — they were really produced —
 *   but the search itself must never imply it saw the whole workspace.
 * - **A candidate the workspace barely knows is counted, not scored.** No
 *   provider call, no match row, no low score invented out of missing evidence.
 * - **Candidates are processed one at a time, in this single job.** The run is
 *   not fanned out into per-candidate jobs: {@see ReplaceSourcingMatchAnalysis}
 *   can only lock a (job, candidate) row that already exists, so two concurrent
 *   first-time assessments of the same pairing would race on the insert.
 *   Sequential processing is what makes that impossible, and `ShouldBeUnique`
 *   per `job_id:generation` keeps a second copy of the same run off the queue.
 */
class SourceCandidatesForJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * One attempt, unlike the per-application analysis.
     *
     * A retry here would replay the whole sweep, paying again for every
     * candidate already assessed before the failing one. A failed search is
     * therefore reported as failed and left for a human to re-run deliberately,
     * which is also the honest thing to show: the recruiter learns the workspace
     * was not fully reviewed instead of watching a silent, expensive loop.
     */
    public int $tries = 1;

    /** A sweep is many provider calls, not one. */
    public int $timeout = 1800;

    public int $uniqueFor = 3600;

    public const MODEL = 'gpt-4o-mini';

    public const OPERATION = 'candidate_sourcing_match';

    public const PROVIDER = 'openai';

    public const QUEUE = 'ai-sourcing-search';

    public function __construct(
        public readonly int $jobId,
        public readonly ?int $userId,
        public readonly int $generation,
    ) {
        $this->queue = self::QUEUE;
    }

    public function uniqueId(): string
    {
        return $this->jobId.':'.$this->generation;
    }

    public function handle(
        ReplaceSourcingMatchAnalysis $replaceSourcingMatchAnalysis,
        CandidateSourcingEligibilityService $eligibility,
        CandidateSourcingContextSanitizer $contextSanitizer,
        AiUsageTracker $usageTracker,
        AiCredentialsResolver $credentialsResolver,
        LimitManager $limitManager,
    ): void {
        $job = Job::query()->with(['company', 'jobCriteria'])->find($this->jobId);

        if ($job === null) {
            return;
        }

        $search = $job->sourcingSearch()->first();

        // A newer run was requested while this one waited in the queue. It owns
        // the search row now, so this instance stops silently rather than
        // writing progress the recruiter did not ask for.
        if (! $search instanceof SourcingSearch || $search->generation !== $this->generation) {
            return;
        }

        // Criteria can be sent back for review between the request and this
        // moment. The gate is checked again for the same reason candidate
        // evaluation checks it twice, and an abandoned run is reset to
        // "not started": it produced no current picture, and the workspace
        // derives the "criteria not confirmed" explanation from the job itself.
        if (! $job->hasConfirmedCriteria() || $job->jobCriteria->isEmpty()) {
            $this->updateCurrentGeneration([
                'status' => SourcingSearchStatus::NotStarted,
                'started_at' => null,
                'completed_at' => null,
            ]);

            return;
        }

        $expectedCriteriaGeneration = (int) $job->criteria_generation;

        if ($this->updateCurrentGeneration(['status' => SourcingSearchStatus::Processing]) === 0) {
            return;
        }

        $configuration = $credentialsResolver->resolve($job->company);
        $model = $configuration->usesOwnKey ? $configuration->model : self::MODEL;

        // The agent and its instructions are built once: every candidate in this
        // run is judged against the same criteria snapshot, by the same prompt.
        $agent = new ScoreCandidateForSourcing($job);
        $instructions = (string) $agent->instructions();

        $runtimeProvider = $credentialsResolver->registerRuntimeProvider($job->company, $configuration);

        $considered = 0;
        $matched = 0;
        $insufficient = 0;

        try {
            foreach ($eligibility->eligibleCandidates($job) as $candidate) {
                $considered++;

                // Read *before* the material is gathered, never after: a CV that
                // becomes readable, is archived or is deleted between this line
                // and the persistence transaction will not match, and the answer
                // is dropped rather than stored as if it had read it. Taking the
                // revision afterwards would leave exactly that gap open.
                $materialsRevision = (int) $candidate->materials_revision;

                // Sufficiency is judged on the sanitized material, so a candidate
                // whose record is only their own name and contact details cannot
                // look knowable enough to score.
                $candidateContext = $contextSanitizer->sanitize($candidate, $eligibility->materialsFor($candidate));

                if (! $eligibility->hasSufficientInformation($candidateContext->materials)) {
                    $insufficient++;

                    if ($this->persistProgress($considered, $matched, $insufficient) === 0) {
                        return;
                    }

                    continue;
                }

                $context = $agent->candidateContext($candidateContext);
                $fingerprint = implode("\n---\n", [
                    ScoreCandidateForSourcing::CACHE_SCHEMA_VERSION,
                    'job_id:'.$job->getKey(),
                    'criteria_generation:'.$expectedCriteriaGeneration,
                    $instructions,
                    $context,
                ]);

                $cached = AiAgentResponseCache::lookup(self::OPERATION, $model, $fingerprint);

                if ($cached !== null) {
                    $scores = $cached['scores'] ?? null;

                    if (! is_array($scores)) {
                        throw new UnexpectedValueException('The cached candidate sourcing response did not contain the expected structured output.');
                    }

                    // A cached answer costs no allowance, so it is used before
                    // the quota is consulted — and it is bound to its criteria
                    // revision exactly like a fresh one.
                    $persisted = $replaceSourcingMatchAnalysis->handle(
                        $search,
                        $candidate,
                        $scores,
                        $candidateContext->materials,
                        $this->generation,
                        $expectedCriteriaGeneration,
                        $materialsRevision,
                    );

                    if ($persisted === null) {
                        // A newer run has taken over, the criteria moved on, or
                        // this candidate's material changed underneath the cached
                        // answer. A cached response is bound to the material
                        // revision it describes exactly like a fresh one.
                        return;
                    }

                    $matched++;

                    if ($this->persistProgress($considered, $matched, $insufficient) === 0) {
                        return;
                    }

                    continue;
                }

                if (! $configuration->usesOwnKey && $limitManager->usage($job->company, Limit::AiAnalyses)->isReached) {
                    // The allowance ran out mid-sweep. The remaining candidates
                    // are not visited and the search does not complete: what it
                    // reports is what it actually reviewed, and the workspace can
                    // see that the rest is still unseen. This candidate is taken
                    // back off the reviewed count — reaching them is not the same
                    // as having assessed them.
                    $this->persistProgress($considered - 1, $matched, $insufficient, SourcingSearchStatus::PendingQuota);

                    return;
                }

                $startedAt = hrtime(true);
                $usageRecord = $usageTracker->startForJob(
                    $job,
                    $this->userId,
                    (string) Str::uuid(),
                    self::OPERATION,
                    self::PROVIDER,
                    $model,
                    $configuration->usesOwnKey,
                );

                try {
                    $response = $agent->prompt(
                        $context,
                        provider: $runtimeProvider,
                        model: $configuration->usesOwnKey ? $configuration->model : null,
                    );

                    if (! $response instanceof StructuredAgentResponse) {
                        throw new UnexpectedValueException('The candidate sourcing agent did not return structured output.');
                    }

                    $scores = $response->toArray()['scores'] ?? null;

                    if (! is_array($scores)) {
                        throw new UnexpectedValueException('The candidate sourcing agent response did not contain the expected structured output.');
                    }

                    $persisted = $replaceSourcingMatchAnalysis->handle(
                        $search,
                        $candidate,
                        $scores,
                        // The same ordered list the context was built from, so the
                        // response's material indexes still mean what they meant
                        // when the request was made.
                        $candidateContext->materials,
                        $this->generation,
                        $expectedCriteriaGeneration,
                        $materialsRevision,
                    );
                    $usageTracker->complete($usageRecord, $response->usage, $this->elapsedMilliseconds($startedAt));
                    AiAgentResponseCache::remember(self::OPERATION, $model, $fingerprint, $response->toArray());

                    // A stale revision makes the answer unusable, not the call a
                    // failure: the provider really did the work, so the usage
                    // record stays honest and the response stays cached under
                    // its own fingerprint. What it must not become is part of a
                    // result presented as current.
                    if ($persisted === null) {
                        return;
                    }
                } catch (Throwable $exception) {
                    $usageTracker->fail($usageRecord, $this->elapsedMilliseconds($startedAt));

                    throw $exception;
                }

                $matched++;

                if ($this->persistProgress($considered, $matched, $insufficient) === 0) {
                    return;
                }
            }

            // Every eligible candidate was visited. This is the only path that
            // may claim a complete result.
            $this->persistProgress($considered, $matched, $insufficient, SourcingSearchStatus::Completed);
        } catch (Throwable $exception) {
            // One candidate's failure ends the whole run rather than being
            // skipped. A skipped candidate would be indistinguishable, in the
            // summary the recruiter reads, from one who was genuinely reviewed
            // and did not match — a silent gap presented as a complete sweep.
            // Failing loudly keeps "reviewed" meaning reviewed; the recruiter
            // re-runs, and the candidates already assessed come back from the
            // response cache without paying for them twice.
            $this->persistProgress($considered, $matched, $insufficient, SourcingSearchStatus::Failed);

            throw $exception;
        } finally {
            if ($runtimeProvider !== null) {
                $credentialsResolver->forgetRuntimeProvider($job->company);
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->updateCurrentGeneration(['status' => SourcingSearchStatus::Failed]);

        // A run that was killed rather than caught (timeout, worker restart) can
        // leave a usage record pending. Only this job's own operation is touched,
        // and only for this job, which `ShouldBeUnique` keeps to a single run.
        AiUsageRecord::query()
            ->where('job_id', $this->jobId)
            ->where('operation', self::OPERATION)
            ->where('status', AiUsageStatus::Pending)
            ->update(['status' => AiUsageStatus::Failed]);
    }

    /**
     * Write the counters reached so far, and optionally the run's outcome.
     *
     * Progress is persisted as it happens so the workspace can show a sweep
     * advancing, and — more importantly — so a run that stops early has already
     * recorded exactly how far it got.
     *
     * @return int The number of rows written: zero means a newer run owns the
     *             search and this one must stop touching it.
     */
    private function persistProgress(
        int $considered,
        int $matched,
        int $insufficient,
        ?SourcingSearchStatus $status = null,
    ): int {
        $attributes = [
            'candidates_considered' => $considered,
            'matches_found' => $matched,
            'insufficient_count' => $insufficient,
        ];

        if ($status !== null) {
            $attributes['status'] = $status;
            $attributes['completed_at'] = $status === SourcingSearchStatus::Completed ? now() : null;
        }

        return $this->updateCurrentGeneration($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return int Rows updated — zero when the search has moved to a newer
     *             generation.
     */
    private function updateCurrentGeneration(array $attributes): int
    {
        return SourcingSearch::query()
            ->where('job_id', $this->jobId)
            ->where('generation', $this->generation)
            ->update($attributes);
    }

    private function elapsedMilliseconds(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }
}
