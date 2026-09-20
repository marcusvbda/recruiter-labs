<?php

namespace App\Services;

use App\Data\CandidateImportProgress;
use App\Enums\CandidateImportFailureCode as FailureCode;
use App\Enums\CandidateImportRefusalCode;
use App\Enums\CandidateImportRowStatus;
use App\Enums\CandidateImportStatus;
use App\Exceptions\CandidateImportRefused;
use App\Jobs\ExecuteCandidateImport;
use App\Models\CandidateImportBatch;
use App\Models\CandidateImportRow;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The worker that turns a confirmed manifest into a changed workspace.
 *
 * Confirmation deliberately starts nothing: it freezes the decision and
 * leaves the batch in "processing" with `confirmed_at` set, which is the
 * signal this service looks for. Everything here is built around one
 * assumption — that the process doing the work can disappear at any moment.
 * A queue worker can be killed halfway, a deploy can restart it, a job can be
 * delivered twice, and the recruiter can close the tab that started it. None
 * of those may produce a second candidate, a second copy of a CV or a second
 * count of anything.
 *
 * Three mechanisms hold that together:
 *
 * - **The claim.** `executing_company_id` is unique across the whole table,
 *   so the database itself guarantees at most one running import per
 *   workspace, and a double-clicked confirm finds the batch already claimed
 *   and dispatches nothing.
 * - **The generation.** Every claim bumps `execution_generation`, and a
 *   worker whose generation is no longer the batch's returns immediately
 *   rather than racing the one that replaced it.
 * - **The row.** A row with `committed_at` is never rerun, and progress is
 *   recounted from the rows rather than accumulated, so replaying a job
 *   cannot inflate a number.
 *
 * What it never does is decide anything the recruiter did not: it creates no
 * application, consumes no allowance, sets no stage, sends no message and
 * asks no model anything. It also does not wait for CV text extraction —
 * {@see CandidateMaterialStorage} schedules that separately, and a candidate
 * whose text arrives after the import finished is still a candidate that was
 * imported.
 */
class CandidateImportExecution
{
    /** Rows between two recounts of the progress written to the batch. */
    private const PROGRESS_EVERY = 25;

    public function __construct(
        private readonly CandidateImportRowExecutor $rows,
        private readonly CandidateMaterialAccess $access,
    ) {}

    /**
     * Claim a confirmed batch and queue the work.
     *
     * Safe to call twice: the second call finds the batch already claimed and
     * returns `null` without dispatching, which is what a double-clicked
     * button, a retried request and a refreshed page all look like from here.
     *
     * @return int|null the generation queued, or `null` if it was already running
     *
     * @throws CandidateImportRefused
     * @throws AuthorizationException
     */
    public function start(CandidateImportBatch $batch, User $actor): ?int
    {
        $generation = DB::transaction(function () use ($batch, $actor): ?int {
            $this->access->lockAndAuthorize($actor, (int) $batch->company_id);

            /** @var CandidateImportBatch $locked */
            $locked = CandidateImportBatch::query()
                ->where('company_id', (int) $batch->company_id)
                ->lockForUpdate()
                ->findOrFail($batch->getKey());

            if ($locked->status !== CandidateImportStatus::Processing || $locked->confirmed_at === null) {
                throw CandidateImportRefused::make(
                    CandidateImportRefusalCode::NotOpen,
                    ['status' => $locked->status->value],
                    'Only a confirmed import can be executed.',
                );
            }

            if ($locked->executing_company_id !== null) {
                return null;
            }

            $locked->forceFill([
                'executing_by_id' => (int) $actor->getKey(),
                'executing_company_id' => (int) $locked->company_id,
                'execution_generation' => (int) $locked->execution_generation + 1,
                'last_progress_at' => now(),
                'failure_code' => null,
            ]);

            try {
                $locked->save();
            } catch (UniqueConstraintViolationException) {
                // Another batch in this workspace holds the claim. The
                // database is the arbiter here precisely because two workers
                // can reach this line at the same instant.
                throw CandidateImportRefused::make(
                    CandidateImportRefusalCode::ExecutionInProgress,
                    ['batch' => (int) $locked->getKey()],
                    'Another import is already running in this workspace.',
                );
            }

            $batch->setRawAttributes($locked->getAttributes(), true);

            return (int) $locked->execution_generation;
        });

        if ($generation !== null) {
            $companyId = (int) $batch->company_id;
            $batchId = (int) $batch->getKey();
            ExecuteCandidateImport::dispatch($companyId, $batchId, $generation);
        }

        return $generation;
    }

    /**
     * Pick a paused import back up, on behalf of whoever is asking now.
     *
     * Resuming is deliberately a human action rather than something a sweeper
     * does quietly: an import pauses because the workspace changed under it —
     * access was lost, a worker died, the execution broke — and all of those
     * are worth a person looking before more candidates are written.
     *
     * It is also deliberately not restricted to the member who confirmed or
     * to the one whose worker died: the person who can fix a workspace on
     * Monday is rarely the one who left it broken on Friday. The resuming
     * actor becomes the executing actor, so what happens from here is
     * attributed to them, while every row already committed keeps the
     * attribution it earned under whoever ran it. Nothing is re-attributed
     * retroactively, and nothing already committed is touched at all: the
     * fresh run's row list is exactly the rows with no `committed_at` and no
     * `result_erased_at`.
     *
     * @return int|null the generation queued, or `null` if a run already holds the workspace
     *
     * @throws CandidateImportRefused
     * @throws AuthorizationException
     */
    public function resume(CandidateImportBatch $batch, User $actor): ?int
    {
        DB::transaction(function () use ($batch, $actor): void {
            $this->access->lockAndAuthorize($actor, (int) $batch->company_id);

            /** @var CandidateImportBatch $locked */
            $locked = CandidateImportBatch::query()
                ->where('company_id', (int) $batch->company_id)
                ->lockForUpdate()
                ->findOrFail($batch->getKey());

            $this->assertResumable($locked);

            $locked->forceFill([
                'status' => CandidateImportStatus::Processing,
                'executing_by_id' => (int) $actor->getKey(),
                'failure_code' => null,
                'resumed_at' => now(),
                'last_progress_at' => now(),
                // Resuming is real work on the import, so the retention window
                // moves with it. A paused batch nobody comes back to keeps its
                // original deadline and expires on time.
                'expires_at' => now()->toImmutable()->addDays(CandidateImportLimits::RETENTION_DAYS),
            ])->save();

            $batch->setRawAttributes($locked->getAttributes(), true);
        });

        // Everything else — the claim, the generation bump, the dispatch — is
        // the same machinery a first execution goes through, on purpose. A
        // resumed import is not a second kind of run.
        return $this->start($batch, $actor);
    }

    /**
     * Try the rows that did not make it, once more, as a normal run.
     *
     * The candidates for a retry are the rows the last pass left needing a
     * human or failed, from the manifest that was already confirmed — never a
     * row carrying `committed_at`, because that row's candidate exists and
     * re-running it would be the second import this whole service is built to
     * prevent, and never a tombstoned one, because its result was deliberately
     * erased afterwards and reproducing it would undo somebody's deletion.
     *
     * Their result fields are cleared so the recount describes this attempt
     * rather than the last one, and the manifest is left exactly as confirmed:
     * a retry re-attempts the same instruction, it does not reinterpret it. A
     * row whose drift is genuine — the reviewed candidate really is gone —
     * lands back in review after one more attempt, which is the honest answer
     * and needs no attempt counter to reach.
     *
     * @return int|null the generation queued, or `null` if a run already holds the workspace
     *
     * @throws CandidateImportRefused
     * @throws AuthorizationException
     */
    public function retry(CandidateImportBatch $batch, User $actor): ?int
    {
        DB::transaction(function () use ($batch, $actor): void {
            $this->access->lockAndAuthorize($actor, (int) $batch->company_id);

            /** @var CandidateImportBatch $locked */
            $locked = CandidateImportBatch::query()
                ->where('company_id', (int) $batch->company_id)
                ->lockForUpdate()
                ->findOrFail($batch->getKey());

            $this->assertRetryable($locked);

            $this->reopenUnresolvedRows($locked);

            $locked->forceFill([
                'status' => CandidateImportStatus::Processing,
                'executing_by_id' => (int) $actor->getKey(),
                'failure_code' => null,
                'completed_at' => null,
                'resumed_at' => now(),
                'last_progress_at' => now(),
                'expires_at' => now()->toImmutable()->addDays(CandidateImportLimits::RETENTION_DAYS),
            ])->save();

            $batch->setRawAttributes($locked->getAttributes(), true);
        });

        return $this->start($batch, $actor);
    }

    /** How many rows a retry would actually attempt, for a screen to offer or hide it. */
    public function retryableRows(CandidateImportBatch $batch): int
    {
        return $this->unresolvedRows((int) $batch->company_id, (int) $batch->getKey())->count();
    }

    /**
     * Hand a stalled import back to the workspace as something a human may resume.
     *
     * The dead worker is not raced: pausing the batch is itself what disowns
     * it, because a worker whose batch is no longer `processing` fails
     * {@see self::isCurrentRun()} and returns without writing. The generation
     * then moves when somebody resumes, through {@see self::start()}, so a
     * worker that wakes up hours later can never share a claim with the run
     * that replaced it. There is no second mechanism here, and deliberately no
     * automatic continuation: the import stops being invisible, and a person
     * decides whether it should carry on.
     *
     * @return bool whether this call is the one that reclaimed it
     */
    public function reclaim(CandidateImportBatch $batch): bool
    {
        return (bool) DB::transaction(function () use ($batch): bool {
            /** @var CandidateImportBatch|null $locked */
            $locked = CandidateImportBatch::query()
                ->where('company_id', (int) $batch->company_id)
                ->lockForUpdate()
                ->find($batch->getKey());

            if ($locked === null || ! $this->isStalled($locked)) {
                return false;
            }

            $this->pause($locked, FailureCode::ExecutionStalled);
            $batch->setRawAttributes($locked->getAttributes(), true);

            return true;
        });
    }

    /**
     * Nobody has heard from this import's worker for long enough to assume it died.
     *
     * A long import is not a stalled one: the worker touches
     * `last_progress_at` on every single row, so silence for half an hour
     * means the process is gone, not that the row it is on is slow.
     */
    public function isStalled(CandidateImportBatch $batch): bool
    {
        return $batch->status === CandidateImportStatus::Processing
            && $batch->executing_company_id !== null
            && $batch->last_progress_at !== null
            && $batch->last_progress_at->lt(now()->subMinutes(CandidateImportLimits::STALLED_MINUTES));
    }

    /** @throws CandidateImportRefused */
    private function assertResumable(CandidateImportBatch $batch): void
    {
        if ($batch->status !== CandidateImportStatus::Paused || $batch->confirmed_at === null) {
            throw CandidateImportRefused::make(
                CandidateImportRefusalCode::NotOpen,
                ['status' => $batch->status->value],
                'Only a paused import can be resumed.',
            );
        }

        $this->assertDetailsPresent($batch);
    }

    /** @throws CandidateImportRefused */
    private function assertRetryable(CandidateImportBatch $batch): void
    {
        $retryable = in_array($batch->status, [
            CandidateImportStatus::Paused,
            CandidateImportStatus::CompletedWithIssues,
            CandidateImportStatus::Failed,
        ], true);

        if (! $retryable || $batch->confirmed_at === null) {
            throw CandidateImportRefused::make(
                CandidateImportRefusalCode::NotOpen,
                ['status' => $batch->status->value],
                'Only a finished import with unresolved rows can be retried.',
            );
        }

        $this->assertDetailsPresent($batch);

        if ($this->unresolvedRows((int) $batch->company_id, (int) $batch->getKey())->count() === 0) {
            throw CandidateImportRefused::make(
                CandidateImportRefusalCode::NothingSelected,
                ['batch' => (int) $batch->getKey()],
                'This import has no rows left to retry.',
            );
        }
    }

    /**
     * An import whose detail has expired cannot be continued from.
     *
     * Retention cleared the manifests, which are the only instruction
     * execution ever reads. Continuing would not import the remaining rows
     * from a week ago; it would import nothing and say it tried.
     *
     * @throws CandidateImportRefused
     */
    private function assertDetailsPresent(CandidateImportBatch $batch): void
    {
        if ($batch->details_expired_at !== null) {
            throw CandidateImportRefused::make(
                CandidateImportRefusalCode::NotOpen,
                ['status' => $batch->status->value],
                'This import is past its retention window; its remaining rows can no longer be executed.',
            );
        }
    }

    /**
     * The rows a retry may touch: unresolved, never committed, never erased.
     *
     * @return Builder<CandidateImportRow>
     */
    private function unresolvedRows(int $companyId, int $batchId): Builder
    {
        return CandidateImportRow::query()
            ->where('company_id', $companyId)
            ->where('batch_id', $batchId)
            ->where('selected', true)
            ->whereIn('status', [
                CandidateImportRowStatus::NeedsReview->value,
                CandidateImportRowStatus::Failed->value,
            ])
            ->whereNull('committed_at')
            ->whereNull('result_erased_at')
            ->whereNotNull('manifest');
    }

    /** Put the unresolved rows back to pending, keeping their confirmed manifest. */
    private function reopenUnresolvedRows(CandidateImportBatch $batch): void
    {
        $this->unresolvedRows((int) $batch->company_id, (int) $batch->getKey())
            ->update([
                'status' => CandidateImportRowStatus::Pending->value,
                'candidate_outcome' => null,
                'material_outcome' => null,
                'failure_code' => null,
                'updated_at' => now(),
            ]);
    }

    /**
     * Walk the confirmed rows of one batch, in the order the file had them.
     *
     * The row list is taken once, up front, so a row is attempted at most
     * once per run: a row that ends up needing review does not get picked up
     * again by the same pass, and rows that committed in an earlier run are
     * never in the list at all.
     */
    public function run(int $companyId, int $batchId, int $generation): void
    {
        $batch = CandidateImportBatch::query()->where('company_id', $companyId)->find($batchId);

        if ($batch === null || ! $this->isCurrentRun($batch, $generation)) {
            return;
        }

        /** @var User|null $actor */
        $actor = User::query()->find($batch->executing_by_id);

        if ($actor === null) {
            $this->pause($batch);

            return;
        }

        try {
            $pending = CandidateImportRow::query()
                ->where('company_id', $companyId)
                ->where('batch_id', $batchId)
                ->where('selected', true)
                ->whereNull('committed_at')
                ->whereNull('result_erased_at')
                ->whereNotNull('manifest')
                ->orderBy('record_number')
                ->pluck('id');

            $since = 0;

            foreach ($pending as $rowId) {
                if (! $this->isCurrentRun($batch->refresh(), $generation)) {
                    return;
                }

                try {
                    $this->rows->execute($batch, $actor, (int) $rowId);
                } catch (AuthorizationException) {
                    // The importer lost the workspace, or the workspace lost
                    // the Candidates feature. Everything already committed
                    // stays committed and attributed; the rest waits for
                    // somebody who is still allowed to do it.
                    $this->pause($batch);

                    return;
                }

                $since++;
                $this->progressed($batch, $since >= self::PROGRESS_EVERY);
                $since = $since >= self::PROGRESS_EVERY ? 0 : $since;
            }

            $this->finish($batch);
        } catch (Throwable $exception) {
            $this->fail($batch, FailureCode::ExecutionFailed);

            throw $exception;
        }
    }

    /**
     * The run this worker belongs to is still the batch's current one.
     *
     * A stale worker — a duplicate delivery, or one that woke up after the
     * batch was resumed by somebody else — stops here rather than working
     * alongside the run that replaced it.
     */
    private function isCurrentRun(CandidateImportBatch $batch, int $generation): bool
    {
        return $batch->status === CandidateImportStatus::Processing
            && $batch->confirmed_at !== null
            && (int) $batch->execution_generation === $generation
            && $batch->executing_company_id !== null;
    }

    /**
     * Record that the import is alive and, periodically, how far it has got.
     *
     * `last_progress_at` moves on every row, because that is what tells a
     * later sweeper the difference between a slow import and a dead one. The
     * counters are recounted less often, because recounting is a read of the
     * whole batch and the screen does not need it per row.
     */
    private function progressed(CandidateImportBatch $batch, bool $recount): void
    {
        if (! $recount) {
            CandidateImportBatch::query()->whereKey($batch->getKey())->update(['last_progress_at' => now()]);

            return;
        }

        $this->persist($batch, ['last_progress_at' => now()]);
    }

    /** Every selected row reached a result; say which kind of ending that was. */
    private function finish(CandidateImportBatch $batch): void
    {
        $progress = CandidateImportProgress::measure((int) $batch->company_id, (int) $batch->getKey());
        $wasTerminal = $this->isTerminal($batch);

        $this->persist($batch, [
            // Rows still needing a human are not a failed import: the
            // candidates that did import are in the pool and usable, and the
            // remainder is a shorter list of work, not a lost one.
            'status' => $progress->unresolved() > 0
                ? CandidateImportStatus::CompletedWithIssues
                : CandidateImportStatus::Completed,
            'failure_code' => null,
            'completed_at' => now(),
            'last_progress_at' => now(),
            'executing_company_id' => null,
        ], $progress);

        // Only the transition into an ending is news. A batch that was already
        // finished and is written again says nothing new, so it says nothing.
        if ($wasTerminal) {
            return;
        }

        $imported = $progress->created + $progress->reused;
        $unresolved = $progress->unresolved();

        DB::afterCommit(function () use ($batch, $imported, $unresolved): void {
            app(RecruiterNotifier::class)->candidateImportCompleted($batch, $imported, $unresolved);
        });
    }

    /** Stop, keep everything committed, and wait for someone who may continue. */
    public function pause(CandidateImportBatch $batch, FailureCode $code = FailureCode::AccessLost): void
    {
        $this->persist($batch, [
            'status' => CandidateImportStatus::Paused,
            'failure_code' => $code->value,
            'last_progress_at' => now(),
            'executing_company_id' => null,
        ]);
    }

    /**
     * The execution itself broke, not one row of it.
     *
     * The batch still shows what it committed, because hiding real imported
     * candidates behind a red banner would send a recruiter looking for
     * people who are already in their pool.
     */
    public function fail(CandidateImportBatch $batch, FailureCode $code = FailureCode::ExecutionFailed): void
    {
        $wasTerminal = $this->isTerminal($batch);

        $this->persist($batch, [
            'status' => CandidateImportStatus::Failed,
            'failure_code' => $code->value,
            'completed_at' => now(),
            'last_progress_at' => now(),
            'executing_company_id' => null,
        ]);

        if ($wasTerminal) {
            return;
        }

        DB::afterCommit(function () use ($batch): void {
            app(RecruiterNotifier::class)->candidateImportFailed($batch);
        });
    }

    /** An ending already reached: writing it again is not a new ending. */
    private function isTerminal(CandidateImportBatch $batch): bool
    {
        return in_array($batch->status, [
            CandidateImportStatus::Completed,
            CandidateImportStatus::CompletedWithIssues,
            CandidateImportStatus::Failed,
        ], true);
    }

    /** A worker that died without answering, seen from the queue's side. */
    public function crashed(int $companyId, int $batchId, int $generation): void
    {
        $batch = CandidateImportBatch::query()->where('company_id', $companyId)->find($batchId);

        if ($batch !== null && $this->isCurrentRun($batch, $generation)) {
            $this->fail($batch);
        }
    }

    /** The counters as they stand, for a screen or a report to read. */
    public function progress(CandidateImportBatch $batch): CandidateImportProgress
    {
        return CandidateImportProgress::measure((int) $batch->company_id, (int) $batch->getKey());
    }

    /**
     * Write batch state and the recounted progress together.
     *
     * The confirmation summary is kept intact underneath: it is what the
     * recruiter agreed to, and the progress is what happened. Both are worth
     * reading side by side, and neither may overwrite the other.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function persist(CandidateImportBatch $batch, array $attributes, ?CandidateImportProgress $progress = null): void
    {
        $progress ??= CandidateImportProgress::measure((int) $batch->company_id, (int) $batch->getKey());

        DB::transaction(function () use ($batch, $attributes, $progress): void {
            /** @var CandidateImportBatch|null $locked */
            $locked = CandidateImportBatch::query()
                ->where('company_id', (int) $batch->company_id)
                ->lockForUpdate()
                ->find($batch->getKey());

            if ($locked === null) {
                return;
            }

            $locked->forceFill([
                ...$attributes,
                'summary' => [...($locked->summary ?? []), 'progress' => $progress->toArray()],
            ])->save();

            $batch->setRawAttributes($locked->getAttributes(), true);
        });
    }
}
