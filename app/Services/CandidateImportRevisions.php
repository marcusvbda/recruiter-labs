<?php

namespace App\Services;

use App\Data\CandidateImportIssue;
use App\Data\CandidateImportSummary;
use App\Enums\CandidateImportRefusalCode;
use App\Enums\CandidateImportStatus;
use App\Exceptions\CandidateImportRefused;
use App\Models\CandidateImportBatch;
use App\Models\CandidateImportRow;
use Carbon\CarbonImmutable;

/**
 * The counter that decides whether what the recruiter reviewed still exists.
 *
 * An import is a conversation held across several requests: a file is
 * uploaded, CVs arrive in batches, a separator is corrected, decisions are
 * made one row at a time, and only then is anything confirmed. Every one of
 * those steps can invalidate the screen the recruiter is looking at, and the
 * product has exactly one way of saying so — `revision`.
 *
 * Two rules, and nothing else:
 *
 * - Changing an *input* (the CSV, the uploaded files, the separator, the
 *   source label) bumps the revision and leaves `validated_revision` behind.
 *   The batch is then unreviewable until it is validated again, because the
 *   preview on screen describes a file that is no longer the file.
 * - Recording a *decision* bumps the revision and moves `validated_revision`
 *   with it. The preview is still true; what changed is the answer. The bump
 *   exists so a second tab holding the previous set of decisions cannot
 *   confirm them over the top of the current ones — its revision is simply no
 *   longer the batch's, and confirmation fails loudly rather than silently
 *   importing an older set of choices.
 *
 * Either way the previous confirmation summary is void: it described an import
 * that would no longer happen.
 */
class CandidateImportRevisions
{
    /**
     * An input changed, so the stored preview no longer describes the batch.
     *
     * Called for a re-uploaded CSV, staged or discarded CV files, a corrected
     * separator and a corrected source label. It deliberately does not clear
     * the rows: the reviewer keeps seeing the previous preview, marked stale,
     * rather than an empty screen while validation runs.
     */
    public function invalidate(CandidateImportBatch $batch): CandidateImportBatch
    {
        // The decisions went with the inputs they were made about. Keeping them
        // would answer questions the new preview has not asked yet, and a row
        // carrying "reuse confirmed" about a candidate the corrected file no
        // longer mentions is worse than one carrying nothing.
        CandidateImportRow::query()
            ->where('company_id', (int) $batch->company_id)
            ->where('batch_id', (int) $batch->getKey())
            ->update(['decision' => null, 'manifest' => null, 'updated_at' => now()]);

        $batch->forceFill([
            'revision' => (int) $batch->revision + 1,
            'summary' => null,
            'failure_code' => null,
            'confirmed_at' => null,
            'confirmed_by_id' => null,
            'confirmed_revision' => null,
            'declaration_at' => null,
            'status' => CandidateImportStatus::Draft,
            'expires_at' => $this->retention(),
        ])->save();

        return $batch;
    }

    /**
     * Validation finished and the rows on file describe the current inputs.
     *
     * The batch becomes "ready for review": the proposed rows and their
     * problems exist, and the next move is the reviewer's — resolve, exclude,
     * confirm or discard. It is the state the recruiter is shown while the
     * import is waiting on them, and nothing else uses it.
     */
    public function markValidated(CandidateImportBatch $batch, CandidateImportSummary $summary): CandidateImportBatch
    {
        $batch->forceFill([
            'validated_revision' => (int) $batch->revision,
            'validated_at' => now(),
            'errors' => null,
            'summary' => $summary->toArray(),
            'status' => CandidateImportStatus::Ready,
        ])->save();

        return $batch;
    }

    /**
     * The file could not be read at all, so there is no preview to review.
     *
     * @param  list<CandidateImportIssue>  $errors
     */
    public function markRefused(CandidateImportBatch $batch, array $errors): CandidateImportBatch
    {
        $batch->forceFill([
            'validated_revision' => null,
            'validated_at' => null,
            'summary' => null,
            'errors' => ['issues' => array_map(fn (CandidateImportIssue $error): array => $error->toArray(), $errors)],
            'status' => CandidateImportStatus::Draft,
        ])->save();

        return $batch;
    }

    /**
     * A reviewer decided something; the preview stands, the confirmation does not.
     *
     * The batch stays ready for review — a decision answers a question, it
     * does not finish the review — and any confirmation it had is void.
     *
     * The retention window moves with this, because a decision is real work
     * the recruiter did. Merely opening the page is not, and does not.
     */
    public function recordDecision(CandidateImportBatch $batch): CandidateImportBatch
    {
        $revision = (int) $batch->revision + 1;

        $batch->forceFill([
            'revision' => $revision,
            'validated_revision' => $revision,
            'summary' => null,
            'confirmed_at' => null,
            'confirmed_by_id' => null,
            'confirmed_revision' => null,
            'declaration_at' => null,
            'status' => CandidateImportStatus::Ready,
            'expires_at' => $this->retention(),
        ])->save();

        return $batch;
    }

    /** Does the stored preview describe the batch's current inputs? */
    public function isReviewable(CandidateImportBatch $batch): bool
    {
        return $batch->validated_revision !== null
            && (int) $batch->validated_revision === (int) $batch->revision;
    }

    /** Is the batch still something a reviewer may change at all? */
    public function isOpen(CandidateImportBatch $batch): bool
    {
        return in_array($batch->status, [
            CandidateImportStatus::Draft,
            CandidateImportStatus::Validating,
            CandidateImportStatus::Ready,
        ], true);
    }

    /** @throws CandidateImportRefused */
    public function assertReviewable(CandidateImportBatch $batch): void
    {
        if (! $this->isOpen($batch)) {
            throw CandidateImportRefused::make(
                CandidateImportRefusalCode::NotOpen,
                ['status' => $batch->status->value],
                'This import can no longer be reviewed.',
            );
        }

        if ($batch->confirmed_at !== null) {
            throw CandidateImportRefused::make(
                CandidateImportRefusalCode::AlreadyConfirmed,
                ['revision' => (int) ($batch->confirmed_revision ?? 0)],
                'This import was already confirmed; its manifest is fixed.',
            );
        }

        if (! $this->isReviewable($batch)) {
            throw CandidateImportRefused::notReviewable(
                (int) ($batch->validated_revision ?? 0),
                (int) $batch->revision,
            );
        }
    }

    /**
     * The revision a caller says it reviewed has to be the one on file.
     *
     * @throws CandidateImportRefused
     */
    public function assertRevision(CandidateImportBatch $batch, int $reviewedRevision): void
    {
        if ((int) $batch->revision !== $reviewedRevision) {
            throw CandidateImportRefused::staleRevision($reviewedRevision, (int) $batch->revision);
        }
    }

    /** Push the retention deadline out; only real work calls this. */
    public function extendRetention(CandidateImportBatch $batch): CandidateImportBatch
    {
        $batch->forceFill(['expires_at' => $this->retention()])->save();

        return $batch;
    }

    public function retention(): CarbonImmutable
    {
        return now()->toImmutable()->addDays(CandidateImportLimits::RETENTION_DAYS);
    }
}
