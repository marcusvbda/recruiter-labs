<?php

namespace App\Data;

/**
 * What one upload call added to a batch.
 *
 * Every file the recruiter selected is described, including the ones that were
 * refused: an upload that vanished without a word would leave the preview
 * saying a row has no CV while the recruiter is certain they sent one. Nothing
 * here is a candidate material — these are batch inputs, and they are attached
 * to nobody until the import is confirmed and executed.
 */
final readonly class CandidateImportStaging
{
    /**
     * @param  list<CandidateImportStagedFile>  $files  Every file recorded by this call, in the order supplied.
     * @param  int  $batchFiles  Files the batch holds in total, including earlier uploads.
     * @param  int  $batchBytes  Bytes the batch holds in total, including earlier uploads.
     */
    public function __construct(
        public array $files = [],
        public int $batchFiles = 0,
        public int $batchBytes = 0,
    ) {}

    /** @return list<CandidateImportStagedFile> */
    public function accepted(): array
    {
        return array_values(array_filter($this->files, fn (CandidateImportStagedFile $file): bool => $file->isUsable()));
    }

    /** @return list<CandidateImportStagedFile> */
    public function rejected(): array
    {
        return array_values(array_filter($this->files, fn (CandidateImportStagedFile $file): bool => ! $file->isUsable()));
    }

    /** @return array{files: int, accepted: int, rejected: int, batch_files: int, batch_bytes: int} */
    public function summary(): array
    {
        return [
            'files' => count($this->files),
            'accepted' => count($this->accepted()),
            'rejected' => count($this->rejected()),
            'batch_files' => $this->batchFiles,
            'batch_bytes' => $this->batchBytes,
        ];
    }
}
