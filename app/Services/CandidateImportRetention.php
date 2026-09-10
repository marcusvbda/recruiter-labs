<?php

namespace App\Services;

use App\Data\CandidateImportRetentionSweep;
use App\Enums\CandidateImportStatus;
use App\Models\CandidateFileCleanup;
use App\Models\CandidateImportBatch;
use App\Models\CandidateImportFile;
use App\Models\CandidateImportRow;
use App\Models\CandidateMaterial;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

/**
 * Forgets an import once it stops being work in progress.
 *
 * An import is the one place in this product that holds a pile of other
 * people's documents and contact details for a reason that expires. The CSV
 * was a transport format, the staged CVs were copies made so a preview could
 * be shown, and the per-row detail existed so a recruiter could correct
 * fourteen rows out of nine hundred. None of that is the workspace's record of
 * anything: the record is the candidates and the CVs the recruiter chose to
 * keep, and those are {@see CandidateMaterial} rows this sweeper
 * never touches, whatever happens to the batch that produced them.
 *
 * Three windows, all {@see CandidateImportLimits::RETENTION_DAYS} long, all
 * measured from the last thing that actually happened:
 *
 * - An unconfirmed draft expires from its own deadline, which
 *   {@see CandidateImportRevisions} moves on a real upload or a real reviewer
 *   decision. Opening the page is not either of those and moves nothing — an
 *   import nobody is working on has to eventually stop holding CVs, and a
 *   dashboard left open on a second monitor cannot keep it alive forever.
 * - A confirmed batch that reached a terminal state keeps its inputs and its
 *   row-level detail for a week after finishing, which is the window in which
 *   somebody downloads the correction file and fixes the rows that did not go
 *   through.
 * - A paused batch keeps them for a week after the last execution or resume.
 *   Resuming pushes that out because it is real work; nothing else does.
 *
 * What is left afterwards is the minimal summary the product promises:
 * workspace, the people responsible, the source label, the times, the final
 * state and the aggregate counts already stored in `summary`. Enough to say
 * "four hundred candidates were imported from this file on that day by that
 * person", and not enough to say who any of them were.
 *
 * Erasure wins over retention, never the other way around. A row whose
 * candidate or CV was deleted in the meantime already carries a tombstone and
 * had its detail removed then; this sweeper clears the same columns to the
 * same nulls and can never put anything back, because it only ever writes
 * nulls and only ever reads the batch's own timestamps.
 */
class CandidateImportRetention
{
    /** Rows and files handled per database round trip. */
    private const CHUNK = 100;

    public function __construct(
        private readonly CandidateImportFileStaging $staging,
        private readonly CandidatePrivateFileCleanup $cleanup,
    ) {}

    /**
     * Discharge every retention obligation that has come due.
     *
     * Chunked from end to end: a workspace that imported every day for a year
     * is a long list of batches, and the sweep has to be able to run inside a
     * scheduled minute without loading it.
     */
    public function sweep(?int $companyId = null): CandidateImportRetentionSweep
    {
        $sweep = new CandidateImportRetentionSweep;

        $this->due($companyId)->each(function (CandidateImportBatch $batch) use (&$sweep): void {
            $sweep = $this->expire($batch, $sweep);
        });

        return $this->discharge($sweep);
    }

    /**
     * The batches whose window may have closed, cheapest filters first.
     *
     * The deadline itself is decided per batch by {@see self::deadline()},
     * because it is read from a different column depending on how the import
     * ended, and expressing that as SQL would hide the rule the product cares
     * about inside a query nobody can read.
     *
     * @return LazyCollection<int, CandidateImportBatch>
     */
    private function due(?int $companyId): LazyCollection
    {
        return CandidateImportBatch::query()
            ->when($companyId !== null, fn ($query) => $query->where('company_id', $companyId))
            // A running import has no deadline: its worker is writing
            // candidates right now. If that worker is dead, stall detection
            // pauses it first, and it becomes due a week after that.
            ->where('status', '!=', CandidateImportStatus::Processing->value)
            ->whereNull('details_expired_at')
            ->orderBy('id')
            ->lazyById(self::CHUNK)
            ->filter(fn (CandidateImportBatch $batch): bool => $this->deadline($batch)->isPast());
    }

    /**
     * When this batch stops being allowed to hold its inputs.
     *
     * Each state is measured from the last real event of that state, and the
     * batch's own `expires_at` is respected wherever something moved it, so a
     * resume or a late reviewer decision is never overridden by an older
     * timestamp elsewhere on the row.
     */
    public function deadline(CandidateImportBatch $batch): CarbonImmutable
    {
        $window = CandidateImportLimits::RETENTION_DAYS;

        $from = match ($batch->status) {
            // Finished for good: the window runs from finishing.
            CandidateImportStatus::Completed,
            CandidateImportStatus::CompletedWithIssues,
            CandidateImportStatus::Failed => $batch->completed_at ?? $batch->last_progress_at,
            // Stopped mid-import: from the last execution or explicit resume.
            CandidateImportStatus::Paused => $batch->last_progress_at ?? $batch->resumed_at ?? $batch->confirmed_at,
            // Thrown away by hand: its files went then, its rows go now.
            CandidateImportStatus::Discarded => $batch->updated_at?->toImmutable(),
            // Never confirmed: `expires_at` alone, which only real work moves.
            default => null,
        };

        $deadline = $from instanceof CarbonImmutable ? $from->addDays($window) : null;
        $declared = $batch->expires_at;

        if ($deadline === null) {
            return $declared;
        }

        return $declared->gt($deadline) ? $declared : $deadline;
    }

    /**
     * Close one batch's window: bytes first, then detail, then the batch.
     *
     * The order matters if the process dies halfway. Deleting the bytes leaves
     * an obligation record behind until they are really gone, so a half-done
     * sweep is retried rather than forgotten, and clearing the batch last
     * means `details_expired_at` is only ever set on a batch whose detail is
     * actually gone.
     */
    public function expire(CandidateImportBatch $batch, ?CandidateImportRetentionSweep $sweep = null): CandidateImportRetentionSweep
    {
        $sweep ??= new CandidateImportRetentionSweep;

        if ($batch->details_expired_at !== null) {
            return $sweep;
        }

        $files = $this->releaseFiles($batch);
        $csvs = $this->releaseCsv($batch);
        $rows = $this->clearRowDetail($batch);
        $expired = $this->markExpired($batch);

        return $sweep->with(
            batches: 1,
            expired: $expired ? 1 : 0,
            csvs: $csvs,
            files: $files,
            rows: $rows,
        );
    }

    /**
     * Remove every staged CV upload this batch made.
     *
     * All of them, not only the rejected ones: a staged file is a copy made to
     * show a preview, and a row that imported successfully had its own copy
     * streamed into the candidate's directory as a
     * {@see CandidateMaterial}. Deleting the staging copy takes
     * nothing away from the candidate, and keeping it would leave a second,
     * unmanaged copy of a real person's CV on disk under an import nobody can
     * open any more.
     */
    private function releaseFiles(CandidateImportBatch $batch): int
    {
        $count = CandidateImportFile::query()
            ->where('company_id', (int) $batch->company_id)
            ->where('batch_id', (int) $batch->getKey())
            ->count();

        if ($count === 0) {
            return 0;
        }

        // The references go before the bytes, for the same reason discarding
        // a draft does it in that order: a crash in between must leave a
        // deletion obligation, never a row still pointing at a deleted file.
        CandidateImportRow::query()
            ->where('company_id', (int) $batch->company_id)
            ->where('batch_id', (int) $batch->getKey())
            ->whereNotNull('file_id')
            ->update(['file_id' => null, 'updated_at' => now()]);

        $this->staging->clear($batch);

        return $count;
    }

    /** Remove the transport file the import was described by. */
    private function releaseCsv(CandidateImportBatch $batch): int
    {
        $path = $batch->csv_path;

        if (! is_string($path) || $path === '') {
            return 0;
        }

        $obligation = DB::transaction(function () use ($batch, $path): CandidateFileCleanup {
            $obligation = $this->cleanup->remember((int) $batch->company_id, $path);

            $batch->forceFill(['csv_path' => null])->save();

            return $obligation;
        });

        $this->cleanup->run($obligation);

        return 1;
    }

    /**
     * Strip the rows back to what a count needs and nothing more.
     *
     * The columns that go are the ones that describe a person: the parsed
     * record, the frozen instruction built from it, the normalised email the
     * matching used, the problems found with it and the reviewer's answer. The
     * columns that stay are the ones that describe an outcome — which record
     * number, what happened to it, whether it committed, whether its result
     * was erased since — so the aggregate counts remain explainable without
     * anybody being identifiable.
     */
    private function clearRowDetail(CandidateImportBatch $batch): int
    {
        $cleared = 0;

        CandidateImportRow::query()
            ->where('company_id', (int) $batch->company_id)
            ->where('batch_id', (int) $batch->getKey())
            ->where(function ($query): void {
                $query->whereNotNull('payload')
                    ->orWhereNotNull('manifest')
                    ->orWhereNotNull('normalized_email')
                    ->orWhereNotNull('issues')
                    ->orWhereNotNull('decision');
            })
            ->orderBy('id')
            ->chunkById(self::CHUNK, function ($rows) use (&$cleared): void {
                $ids = $rows->modelKeys();

                $cleared += CandidateImportRow::query()->whereKey($ids)->update([
                    'payload' => null,
                    'manifest' => null,
                    'normalized_email' => null,
                    'issues' => null,
                    'decision' => null,
                    'updated_at' => now(),
                ]);
            });

        return $cleared;
    }

    /**
     * Mark the batch as summarised, and expire only what never finished.
     *
     * A completed import is history, and history keeps its name: replacing
     * "completed with issues" with "expired" a week later would tell a
     * recruiter that an import which really did add four hundred candidates
     * somehow did not happen. Only a draft nobody confirmed and a paused run
     * nobody came back to become expired, because for those the state was a
     * promise of future work and that work is now impossible — their manifests
     * are gone.
     *
     * @return bool whether the batch's own status became expired
     */
    private function markExpired(CandidateImportBatch $batch): bool
    {
        $unfinished = in_array($batch->status, [
            CandidateImportStatus::Draft,
            CandidateImportStatus::Validating,
            CandidateImportStatus::Ready,
            CandidateImportStatus::Paused,
        ], true);

        $attributes = ['details_expired_at' => now()];

        if ($unfinished) {
            $attributes['status'] = CandidateImportStatus::Expired;
            // No worker may claim an expired batch, and the slot it might
            // still be holding belongs to the workspace, not to it.
            $attributes['executing_company_id'] = null;
        }

        $batch->forceFill($attributes)->save();

        return $unfinished;
    }

    /**
     * Retry the deletions an earlier pass could not complete.
     *
     * Every obligation here outlived the record that pointed at it, so this is
     * the only thing that will ever remove those bytes. A file that keeps
     * failing keeps its `attempts` count and stays visible rather than being
     * dropped after some number of tries, because the alternative is a private
     * disk quietly accumulating documents nobody has a record of.
     */
    private function discharge(CandidateImportRetentionSweep $sweep): CandidateImportRetentionSweep
    {
        $retried = 0;

        CandidateFileCleanup::query()
            ->orderBy('id')
            ->lazyById(self::CHUNK)
            ->each(function (CandidateFileCleanup $obligation) use (&$retried): void {
                $this->cleanup->run($obligation);
                $retried++;
            });

        return $sweep->with(
            retried: $retried,
            outstanding: CandidateFileCleanup::query()->count(),
        );
    }
}
