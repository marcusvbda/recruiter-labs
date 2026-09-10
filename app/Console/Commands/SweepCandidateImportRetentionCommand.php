<?php

namespace App\Console\Commands;

use App\Services\CandidateImportRetention;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Runs the import retention windows, so the product forgets on schedule.
 *
 * The whole rule lives in {@see CandidateImportRetention}; this is only the
 * clock that asks it. It runs often rather than nightly because the thing
 * being deleted is other people's CVs and contact details, and an hour of
 * lateness is cheaper than a day of it — and because the same pass retries
 * deletions the private disk refused earlier, which is the only thing that
 * will ever remove those bytes.
 */
#[Signature('candidate-imports:sweep-retention')]
#[Description('Expire candidate import inputs and row detail past their retention window')]
class SweepCandidateImportRetentionCommand extends Command
{
    public function handle(CandidateImportRetention $retention): int
    {
        $sweep = $retention->sweep();

        $this->components->info(
            "Expired {$sweep->batches} candidate imports ({$sweep->expired} unfinished), "
            ."removing {$sweep->csvs} CSV uploads and {$sweep->files} staged CVs, "
            ."and clearing detail from {$sweep->rows} rows."
        );

        if ($sweep->retried > 0 || $sweep->outstanding > 0) {
            $this->components->info(
                "Retried {$sweep->retried} pending file deletions; {$sweep->outstanding} still outstanding."
            );
        }

        return self::SUCCESS;
    }
}
