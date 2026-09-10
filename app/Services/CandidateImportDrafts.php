<?php

namespace App\Services;

use App\Enums\CandidateImportRefusalCode;
use App\Enums\CandidateImportStatus;
use App\Exceptions\CandidateImportRefused;
use App\Models\CandidateFileCleanup;
use App\Models\CandidateImportBatch;
use App\Models\CandidateImportRow;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * How many imports a workspace may have in flight, and how one is thrown away.
 *
 * The cap is not a licensing device. An unconfirmed import holds uploaded CVs
 * of real people on a private disk for no reason other than that somebody
 * started something and did not finish it, and three of those at once is
 * already more unfinished business than a workspace can keep track of. A
 * fourth is refused with an explanation naming the way out — confirm one or
 * discard one — rather than a bare limit.
 *
 * Discarding is complete and costs the pool nothing: no candidate is created,
 * none is modified, none is deleted. The batch is marked discarded first, so
 * access to its files stops before the bytes go, and the staged uploads are
 * then erased. A recruiter who walks away has to be able to walk away.
 *
 * The retention deadline moves for work, not for attention: uploading a file
 * or deciding a row pushes it out, and opening the page a hundred times does
 * not. An import nobody is actually working on has to eventually stop holding
 * candidates' documents.
 */
class CandidateImportDrafts
{
    public function __construct(
        private CandidateImportFileStaging $staging,
        private CandidateImportRevisions $revisions,
        private CandidatePrivateFileCleanup $cleanup,
        private CandidateMaterialAccess $access,
    ) {}

    /** Imports this workspace has started and not yet finished. */
    public function unconfirmedCount(int $companyId): int
    {
        return (int) CandidateImportBatch::query()
            ->where('company_id', $companyId)
            ->whereIn('status', [
                CandidateImportStatus::Draft->value,
                CandidateImportStatus::Validating->value,
                CandidateImportStatus::Ready->value,
            ])
            ->count();
    }

    public function hasCapacity(int $companyId): bool
    {
        return $this->unconfirmedCount($companyId) < CandidateImportLimits::UNCONFIRMED_BATCHES;
    }

    /** @throws CandidateImportRefused */
    public function assertCapacity(int $companyId): void
    {
        if (! $this->hasCapacity($companyId)) {
            throw CandidateImportRefused::unconfirmedBatchLimit(CandidateImportLimits::UNCONFIRMED_BATCHES);
        }
    }

    /**
     * Start an import, or refuse because the workspace already has too many.
     *
     * The count and the insert share one transaction and one lock on the
     * workspace, so two recruiters clicking at the same second cannot both be
     * told there was room for one more.
     */
    public function start(
        User $actor,
        int $companyId,
        string $sourceLabel,
        string $separator = ',',
        bool $correctionMode = false,
    ): CandidateImportBatch {
        return DB::transaction(function () use ($actor, $companyId, $sourceLabel, $separator, $correctionMode): CandidateImportBatch {
            $this->access->lockAndAuthorize($actor, $companyId);
            $this->assertCapacity($companyId);

            return CandidateImportBatch::query()->create([
                'company_id' => $companyId,
                'uploaded_by_id' => (int) $actor->getKey(),
                'source_label' => $sourceLabel,
                'separator' => $separator,
                'correction_mode' => $correctionMode,
                'status' => CandidateImportStatus::Draft,
                'expires_at' => $this->revisions->retention(),
            ]);
        });
    }

    /**
     * Throw an unconfirmed import away, entirely.
     *
     * Marking the batch and revoking its rows' file references happens before
     * a single byte is deleted: if the erasure fails halfway, what is left
     * behind is an obligation the retention scheduler can discharge, not a
     * discarded import still handing out documents.
     *
     * @throws CandidateImportRefused
     */
    public function discard(CandidateImportBatch $batch, User $actor): void
    {
        /** @var CandidateFileCleanup|null $obligation */
        $obligation = null;

        DB::transaction(function () use ($batch, $actor, &$obligation): void {
            $this->access->lockAndAuthorize($actor, (int) $batch->company_id);

            /** @var CandidateImportBatch $locked */
            $locked = CandidateImportBatch::query()
                ->where('company_id', (int) $batch->company_id)
                ->lockForUpdate()
                ->findOrFail($batch->getKey());

            if (! $this->revisions->isOpen($locked) || $locked->confirmed_at !== null) {
                throw CandidateImportRefused::make(
                    CandidateImportRefusalCode::NotDiscardable,
                    ['status' => $locked->status->value],
                    'This import can no longer be discarded.',
                );
            }

            CandidateImportRow::query()
                ->where('company_id', (int) $locked->company_id)
                ->where('batch_id', (int) $locked->getKey())
                ->update(['file_id' => null, 'selected' => false, 'manifest' => null, 'updated_at' => now()]);

            $csv = $locked->csv_path;

            if (is_string($csv) && $csv !== '') {
                $obligation = $this->cleanup->remember((int) $locked->company_id, $csv);
            }

            $locked->forceFill([
                'status' => CandidateImportStatus::Discarded,
                'csv_path' => null,
                'summary' => null,
            ])->save();

            $batch->setRawAttributes($locked->getAttributes(), true);
        });

        if ($obligation !== null) {
            $this->cleanup->run($obligation);
        }

        $this->staging->clear($batch);
    }

    /**
     * Real work happened on this import; hold its files a while longer.
     *
     * Called after an upload and after a reviewer decision. Never called from
     * a page render: looking at an import is not working on it.
     */
    public function touch(CandidateImportBatch $batch): CandidateImportBatch
    {
        return $this->revisions->extendRetention($batch);
    }
}
