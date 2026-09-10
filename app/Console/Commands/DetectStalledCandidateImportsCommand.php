<?php

namespace App\Console\Commands;

use App\Enums\CandidateImportStatus;
use App\Models\CandidateImportBatch;
use App\Services\CandidateImportExecution;
use App\Services\CandidateImportLimits;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Finds imports whose worker died and gives them back to their workspace.
 *
 * An import claims its workspace with a row-level lock the database enforces,
 * which is exactly what makes a dead worker a problem: the claim outlives the
 * process holding it, and nothing else in that workspace can start while a
 * corpse owns the slot. The worker touches `last_progress_at` on every row, so
 * silence for {@see CandidateImportLimits::STALLED_MINUTES} is not a slow row
 * — it is a process that is not coming back.
 *
 * This command only reclaims. It does not continue the import, because the
 * reasons a worker disappears — a killed container, a deploy, a database that
 * went away — are worth a person seeing before more candidates are written
 * into their pool. The batch becomes paused and resumable, with the committed
 * rows intact and attributed, and a recruiter decides what happens next.
 */
#[Signature('candidate-imports:detect-stalls')]
#[Description('Pause candidate imports whose worker stopped reporting progress')]
class DetectStalledCandidateImportsCommand extends Command
{
    public function handle(CandidateImportExecution $execution): int
    {
        $reclaimedCount = 0;

        CandidateImportBatch::query()
            ->where('status', CandidateImportStatus::Processing->value)
            ->whereNotNull('executing_company_id')
            ->whereNotNull('last_progress_at')
            ->where('last_progress_at', '<', now()->subMinutes(CandidateImportLimits::STALLED_MINUTES))
            ->orderBy('id')
            ->eachById(function (CandidateImportBatch $batch) use ($execution, &$reclaimedCount): void {
                // The reclaim re-reads the batch under a lock, so an import
                // that reported progress between this query and this line is
                // left alone rather than paused out from under a live worker.
                if ($execution->reclaim($batch)) {
                    $reclaimedCount++;
                }
            }, 100);

        $this->components->info("Paused {$reclaimedCount} stalled candidate imports.");

        return self::SUCCESS;
    }
}
