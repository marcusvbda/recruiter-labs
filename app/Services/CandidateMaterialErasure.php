<?php

namespace App\Services;

use App\Enums\CandidateImportRowStatus;
use App\Models\Candidate;
use App\Models\CandidateImportBatch;
use App\Models\CandidateImportFile;
use App\Models\CandidateImportRow;
use App\Models\CandidateMaterial;
use App\Models\Company;
use App\Models\SourcingMatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CandidateMaterialErasure
{
    public function __construct(
        private readonly CandidateMaterialRevision $revision,
        private readonly CandidatePrivateFileCleanup $cleanup,
    ) {}

    /** Caller holds company/candidate locks. Erased rows remain replay tombstones. */
    public function eraseMaterial(CandidateMaterial $material): void
    {
        $companyId = $material->company_id;
        $candidateId = $material->candidate_id;
        // Only the rows that committed this material, never another candidate's
        // rows that happen to reference identical file contents. Their staged
        // files go too, so deleting a retained CV leaves no downloadable
        // temporary copy in an unexpired batch.
        $rows = CandidateImportRow::query()->where('company_id', $companyId)
            ->where('material_id', $material->getKey())->get();
        $fileIds = $rows->pluck('file_id')->filter()->unique()->values();
        $batchIds = $rows->pluck('batch_id')->unique()->values();
        foreach ($rows as $row) {
            $this->eraseRow($row);
        }
        foreach (CandidateImportFile::query()->where('company_id', $companyId)->whereIn('id', $fileIds)->get() as $file) {
            $this->eraseStagedFile($file);
        }
        foreach ($batchIds as $batchId) {
            $this->eraseBatchCsv($companyId, $batchId);
        }
        $material->forceFill([
            'deleted_at' => now(), 'prepared_text' => null, 'original_name' => null,
            'checksum' => null, 'source_label' => null, 'received_on' => null,
            'preparation_generation' => $material->preparation_generation + 1,
            'preparation_error' => null, 'text_is_partial' => false,
        ])->save();
        if ($candidateId !== null) {
            $this->revision->advance($companyId, $candidateId);
            // Keep the human relationship, while removing document-derived output.
            foreach (SourcingMatch::query()->where('company_id', $companyId)->where('candidate_id', $candidateId)->get() as $match) {
                $match->criterionScores()->where('company_id', $companyId)->delete();
                $match->forceFill(['criteria_generation' => 0, 'potential_match' => null,
                    'evidence_coverage' => null, 'confidence' => null, 'analyzed_at' => null])->save();
            }
        }
        DB::afterCommit(fn () => $this->cleanupMaterial($material));
    }

    public function eraseCandidate(Candidate $candidate): void
    {
        DB::transaction(function () use ($candidate): void {
            Company::query()->whereKey($candidate->company_id)->lockForUpdate()->firstOrFail();
            Candidate::query()->where('company_id', $candidate->company_id)->whereKey($candidate->getKey())->lockForUpdate()->firstOrFail();
            foreach ($candidate->materials()->where('company_id', $candidate->company_id)->lockForUpdate()->get() as $material) {
                $this->eraseMaterial($material);
            }
            $rows = CandidateImportRow::query()->where('company_id', $candidate->company_id)
                ->where(function (Builder $query) use ($candidate): void {
                    $query->where('candidate_id', $candidate->getKey())->orWhere('reviewed_candidate_id', $candidate->getKey());
                    if (filled($candidate->email)) {
                        $query->orWhere('normalized_email', Candidate::normalizeEmail($candidate->email));
                    }
                })->get();
            foreach ($rows as $row) {
                if ($row->file_id !== null) {
                    $file = CandidateImportFile::query()->where('company_id', $candidate->company_id)->find($row->file_id);
                    if ($file !== null) {
                        $this->eraseStagedFile($file);
                    }
                }
                $this->eraseRow($row);
            }
            // The raw CSV holds the erased name and email verbatim, so it is
            // erased once per affected batch instead of once per row.
            foreach ($rows->pluck('batch_id')->unique() as $batchId) {
                $this->eraseBatchCsv($candidate->company_id, $batchId);
            }
            Company::query()->whereKey($candidate->company_id)->increment('candidate_pool_revision');
        });
    }

    private function eraseRow(CandidateImportRow $row): void
    {
        $row->forceFill([
            'payload' => null, 'manifest' => null, 'normalized_email' => null,
            'issues' => null, 'decision' => null, 'candidate_id' => null, 'reviewed_candidate_id' => null,
            'material_id' => null, 'file_id' => null, 'result_erased_at' => now(),
            'failure_code' => 'result_erased',
            'status' => $row->committed_at !== null ? $row->status : CandidateImportRowStatus::NeedsReview,
        ])->save();
    }

    /** Batch-level concern: pending rows keep their payload, so they do not need the raw CSV. */
    private function eraseBatchCsv(int $companyId, int $batchId): void
    {
        $batch = CandidateImportBatch::query()->where('company_id', $companyId)->find($batchId);
        if ($batch === null || $batch->csv_path === null) {
            return;
        }
        $cleanup = $this->cleanup->remember($batch->company_id, $batch->csv_path);
        $batch->forceFill(['csv_path' => null, 'csv_original_name' => null])->save();
        DB::afterCommit(fn () => $this->cleanup->run($cleanup));
    }

    public function eraseStagedFile(CandidateImportFile $file): void
    {
        $file->forceFill(['erased_at' => now(), 'original_name' => null, 'association_name' => null, 'checksum' => null, 'errors' => null])->save();
        DB::afterCommit(fn () => $this->cleanupStagedFile($file));
    }

    public function cleanupStagedFile(CandidateImportFile $file): void
    {
        if ($file->erased_at === null) {
            return;
        }
        try {
            if ($file->path !== null && ! Storage::disk('candidate_materials')->delete($file->path)) {
                throw new \RuntimeException('Staged file cleanup failed.');
            }
            $file->forceFill(['path' => null, 'cleanup_failed_at' => null])->save();
        } catch (Throwable) {
            $file->forceFill(['cleanup_failed_at' => now()])->save();
        }
    }

    public function cleanupMaterial(CandidateMaterial $material): void
    {
        if ($material->deleted_at === null) {
            return;
        }
        try {
            if ($material->path !== null && ! Storage::disk('candidate_materials')->delete($material->path)) {
                throw new \RuntimeException('Material file cleanup failed.');
            }
            $material->forceFill(['path' => null, 'cleanup_failed_at' => null])->save();
        } catch (Throwable) {
            $material->forceFill(['cleanup_failed_at' => now()])->save();
        }
    }
}
