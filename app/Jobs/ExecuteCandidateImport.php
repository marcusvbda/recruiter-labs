<?php

namespace App\Jobs;

use App\Services\CandidateImportExecution;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Runs one confirmed import batch's rows. A batch can hold up to 1,000 rows,
 * each doing file I/O against the private disk, so this is given real room to
 * work rather than the short timeout a single-record job would use.
 */
class ExecuteCandidateImport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public int $companyId, public int $batchId, public int $generation) {}

    public function handle(CandidateImportExecution $execution): void
    {
        $execution->run($this->companyId, $this->batchId, $this->generation);
    }

    /**
     * The queue's own verdict that this run died, not a row-level failure the
     * runner already recorded. {@see CandidateImportExecution::run()} answers
     * for every row it actually reaches; this only fires when the worker
     * itself never got to, or never finished, that accounting.
     */
    public function failed(?Throwable $exception): void
    {
        app(CandidateImportExecution::class)->crashed($this->companyId, $this->batchId, $this->generation);
    }
}
