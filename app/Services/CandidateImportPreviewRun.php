<?php

namespace App\Services;

use App\Data\CandidateImportAssociation;
use App\Data\CandidateImportCsvReading;
use App\Models\CandidateImportBatch;

/**
 * One validation pass, with the batch's counters moved to match it.
 *
 * Validation, association and the revision counters are three things that have
 * to agree or the review screen is a lie: rows describing one file, a summary
 * describing another, and a `validated_revision` claiming both are current.
 * They are moved together here so no caller has to remember the order.
 *
 * Call it whenever an input changes — a new CSV, more uploads, a corrected
 * separator or source label — after the change has been recorded on the batch.
 */
class CandidateImportPreviewRun
{
    public function __construct(
        private CandidateImportFileAssociator $associator,
        private CandidateImportReview $review,
        private CandidateImportRevisions $revisions,
    ) {}

    /**
     * Re-read the batch against its current inputs and mark it validated.
     *
     * A reading the reader itself refused never becomes a preview: the batch
     * keeps the refusal, stays unvalidated, and cannot be reviewed or
     * confirmed until a readable file replaces it.
     */
    public function run(
        CandidateImportBatch $batch,
        CandidateImportCsvReading $reading,
        ?string $timezone = null,
    ): CandidateImportAssociation {
        $association = $this->associator->analyzeBatch($batch, $reading, $timezone);

        if (! $association->validation->isValid()) {
            $this->revisions->markRefused($batch, $association->validation->errors);

            return $association;
        }

        $summary = $this->review
            ->summaryOf($batch, $this->review->rows($batch))
            ->withIgnoredEmptyRecords($reading->ignoredEmptyRecords);

        $this->revisions->markValidated($batch, $summary);

        return $association;
    }

    /**
     * Record that an input changed, before the new preview is computed.
     *
     * Bumping first is deliberate: between the change and the next validation
     * the batch is knowingly stale, and a confirmation attempted in that
     * window has to fail rather than confirm the previous file's answers.
     */
    public function invalidate(CandidateImportBatch $batch): CandidateImportBatch
    {
        return $this->revisions->invalidate($batch);
    }
}
