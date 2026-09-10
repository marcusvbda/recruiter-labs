<?php

namespace App\Data;

/**
 * What one pass of the retention sweeper actually discharged.
 *
 * Counted so the scheduled run can say something truthful in a log line: a
 * sweep that expired nothing and a sweep that could not delete anything look
 * identical from the outside otherwise, and the second one needs looking at.
 */
final readonly class CandidateImportRetentionSweep
{
    public function __construct(
        /** Batches whose retention window closed in this pass. */
        public int $batches = 0,
        /** Batches whose own status moved to expired because they never finished. */
        public int $expired = 0,
        /** Original CSV uploads removed. */
        public int $csvs = 0,
        /** Staged CV uploads removed, kept or rejected alike. */
        public int $files = 0,
        /** Rows whose identifying detail was cleared. */
        public int $rows = 0,
        /** Earlier failed deletions retried in this pass. */
        public int $retried = 0,
        /** Deletions still outstanding after this pass. */
        public int $outstanding = 0,
    ) {}

    public function with(
        int $batches = 0,
        int $expired = 0,
        int $csvs = 0,
        int $files = 0,
        int $rows = 0,
        int $retried = 0,
        int $outstanding = 0,
    ): self {
        return new self(
            batches: $this->batches + $batches,
            expired: $this->expired + $expired,
            csvs: $this->csvs + $csvs,
            files: $this->files + $files,
            rows: $this->rows + $rows,
            retried: $this->retried + $retried,
            outstanding: $this->outstanding + $outstanding,
        );
    }
}
