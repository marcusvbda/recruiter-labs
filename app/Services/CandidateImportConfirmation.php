<?php

namespace App\Services;

use App\Data\CandidateImportReviewRow;
use App\Data\CandidateImportSummary;
use App\Enums\CandidateImportRefusalCode;
use App\Enums\CandidateImportStatus;
use App\Exceptions\CandidateImportRefused;
use App\Models\CandidateImportBatch;
use App\Models\CandidateImportRow;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * The moment an import stops being a preview and becomes a promise.
 *
 * Confirmation writes one thing that matters: a manifest per selected row,
 * stating the identity, the document and the received date that execution will
 * apply. Execution reads that and nothing else. It does not re-resolve the
 * email against the pool, does not re-match filenames, does not recompute a
 * date — because the recruiter approved a specific set of outcomes on a
 * specific screen, and a worker that recomputed them an hour later could
 * legitimately reach different ones. A pool that changed in between is a
 * problem for the execution to report, never a licence to import something
 * else.
 *
 * It refuses far more than it accepts. A batch whose file could not be read is
 * refused whole. A batch reviewed at a revision that has since moved on is
 * refused loudly, so a second browser tab cannot replace the current answers
 * with the ones it happens to be holding. A batch with nothing selected is
 * refused, because "import nothing" is not an import. Rows that are still
 * invalid do not block anything: they are simply not selected, counted, and
 * stated in the sentence the recruiter agrees to.
 *
 * It starts nothing. The batch leaves "ready for review" and enters
 * "processing" — because from the recruiter's point of view their decision is
 * now being applied, and leaving a confirmed import labelled as waiting for
 * them would be the screen asking twice for something already given — but no
 * job is dispatched here. Claiming a confirmed batch, doing the row work and
 * recovering from a worker that died is the execution stage's business, and it
 * finds its work by looking for processing batches whose `confirmed_at` is
 * set.
 */
class CandidateImportConfirmation
{
    /** Rows whose manifests are written per statement. */
    private const CHUNK = 200;

    public function __construct(
        private CandidateImportReview $review,
        private CandidateImportRevisions $revisions,
        private CandidateMaterialAccess $access,
    ) {}

    /**
     * Freeze this import as reviewed, or refuse and change nothing.
     *
     * `$reviewedRevision` is the revision the caller had on screen when the
     * recruiter agreed to the sentence. `$candidateProvidedDeclaration` is the
     * importer's own statement that the people in this file supplied their
     * data to be considered for roles. It is recorded as what it is — a
     * statement made by the person importing — and is never treated as
     * evidence that any candidate consented to anything.
     *
     * @throws CandidateImportRefused
     * @throws AuthorizationException
     */
    public function confirm(
        CandidateImportBatch $batch,
        User $actor,
        int $reviewedRevision,
        bool $candidateProvidedDeclaration,
    ): CandidateImportSummary {
        return DB::transaction(function () use ($batch, $actor, $reviewedRevision, $candidateProvidedDeclaration): CandidateImportSummary {
            $this->access->lockAndAuthorize($actor, (int) $batch->company_id);

            /** @var CandidateImportBatch $locked */
            $locked = CandidateImportBatch::query()
                ->where('company_id', (int) $batch->company_id)
                ->lockForUpdate()
                ->findOrFail($batch->getKey());

            $this->revisions->assertReviewable($locked);
            $this->revisions->assertRevision($locked, $reviewedRevision);
            $this->assertReadable($locked);
            $this->assertNothingExecuting($locked);

            if (! $candidateProvidedDeclaration) {
                throw CandidateImportRefused::make(
                    CandidateImportRefusalCode::DeclarationMissing,
                    [],
                    'The importer has to state that these candidates supplied their own data.',
                );
            }

            $rows = $this->review->rows($locked);

            if (count($rows) > CandidateImportLimits::ROWS) {
                throw CandidateImportRefused::make(
                    CandidateImportRefusalCode::RowLimitExceeded,
                    ['limit' => CandidateImportLimits::ROWS, 'rows' => count($rows)],
                    'An import may carry at most '.CandidateImportLimits::ROWS.' rows.',
                );
            }

            $summary = $this->review->summaryOf($locked, $rows);

            if (! $summary->hasSelection()) {
                throw CandidateImportRefused::make(
                    CandidateImportRefusalCode::NothingSelected,
                    ['rows' => count($rows)],
                    'Nothing is selected, so there is nothing to import.',
                );
            }

            $this->writeManifests($locked, $rows);

            $now = now();

            $locked->forceFill([
                'status' => CandidateImportStatus::Processing,
                'confirmed_at' => $now,
                'confirmed_by_id' => (int) $actor->getKey(),
                'confirmed_revision' => (int) $locked->revision,
                'declaration_at' => $now,
                'summary' => $summary->toArray(),
                'expires_at' => $this->revisions->retention(),
            ])->save();

            $batch->setRawAttributes($locked->getAttributes(), true);

            return $summary;
        });
    }

    /**
     * The snapshot execution will read, one row at a time.
     *
     * Rows that are not being imported have their manifest cleared in the same
     * pass. A leftover manifest from an earlier confirmation would be a row
     * describing an import the recruiter has since decided against, and
     * execution reads manifests, not intentions.
     *
     * @param  list<CandidateImportReviewRow>  $rows
     */
    private function writeManifests(CandidateImportBatch $batch, array $rows): void
    {
        $companyId = (int) $batch->company_id;
        $batchId = (int) $batch->getKey();
        $revision = (int) $batch->revision;
        $label = (string) $batch->source_label;
        $now = now();

        /** @var list<int> $cleared */
        $cleared = [];

        foreach ($rows as $row) {
            if (! $row->willImport()) {
                $cleared[] = $row->recordNumber;

                continue;
            }

            CandidateImportRow::query()
                ->where('company_id', $companyId)
                ->where('batch_id', $batchId)
                ->where('record_number', $row->recordNumber)
                ->update([
                    'manifest' => json_encode(
                        $row->manifest($revision, $label),
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                    ),
                    'selected' => true,
                    'status' => $row->resolvedStatus()->value,
                    'updated_at' => $now,
                ]);
        }

        foreach (array_chunk($cleared, self::CHUNK) as $chunk) {
            CandidateImportRow::query()
                ->where('company_id', $companyId)
                ->where('batch_id', $batchId)
                ->whereIn('record_number', $chunk)
                ->update(['manifest' => null, 'selected' => false, 'updated_at' => $now]);
        }
    }

    /**
     * One import runs at a time per workspace, and the second one waits openly.
     *
     * The refusal is deliberate rather than a queue: scheduling this batch to
     * start whenever the other one finishes would apply, an hour later and
     * unattended, a set of decisions taken against a pool that the running
     * import is at that very moment changing. So the batch stays ready for
     * review and unconfirmed, the recruiter is pointed at the import that is
     * running, and they confirm again once they can see what the workspace
     * actually looks like.
     *
     * The check is safe under concurrency because the caller already holds the
     * workspace row lock taken by {@see CandidateMaterialAccess::lockAndAuthorize()},
     * so two confirmations in the same workspace are serialized behind it.
     *
     * @throws CandidateImportRefused
     */
    private function assertNothingExecuting(CandidateImportBatch $batch): void
    {
        $running = CandidateImportBatch::query()
            ->where('company_id', (int) $batch->company_id)
            ->where('id', '!=', $batch->getKey())
            ->where('status', CandidateImportStatus::Processing->value)
            ->value('id');

        if ($running !== null) {
            throw CandidateImportRefused::make(
                CandidateImportRefusalCode::ExecutionInProgress,
                ['batch' => (int) $running],
                'Another import is already running in this workspace. Wait for it to finish, then confirm again.',
            );
        }
    }

    /**
     * A file the reader refused is refused whole, however good its rows look.
     *
     * @throws CandidateImportRefused
     */
    private function assertReadable(CandidateImportBatch $batch): void
    {
        $errors = $batch->errors['issues'] ?? null;

        if (is_array($errors) && $errors !== []) {
            throw CandidateImportRefused::make(
                CandidateImportRefusalCode::StructuralErrors,
                ['errors' => count($errors)],
                'This file could not be read, so no part of it can be imported.',
            );
        }
    }
}
