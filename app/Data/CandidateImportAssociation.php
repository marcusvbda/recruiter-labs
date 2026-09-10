<?php

namespace App\Data;

/**
 * How the batch's uploads and its rows line up, once both are known.
 *
 * `$validation` is the same preview field validation produced, with what the
 * files taught it appended to the rows concerned: a name no upload carries, a
 * name two uploads carry, an upload two rows want, a document that could not be
 * read. `$fileIds` says which upload a row may legitimately be attached to, and
 * holds an entry only for a row whose association is unambiguous and whose file
 * inspected cleanly — an association the product had to guess at is not an
 * association, it is a blocked row.
 *
 * `$notices` carries what belongs to the batch rather than to any row, chiefly
 * the uploads nobody asked for. They are counted and shown; they are never
 * attached to anyone, because a CV whose owner cannot be named is not a
 * candidate the workspace may create.
 */
final readonly class CandidateImportAssociation
{
    /**
     * @param  list<CandidateImportStagedFile>  $files  Every file staged against the batch.
     * @param  array<int, int>  $fileIds  Record number to the file it is attached to.
     * @param  list<CandidateImportIssue>  $notices  Batch-level findings, owned by no single row.
     * @param  list<string>  $unreferenced  Association names no row asks for.
     */
    public function __construct(
        public CandidateImportValidation $validation,
        public array $files = [],
        public array $fileIds = [],
        public array $notices = [],
        public array $unreferenced = [],
    ) {}

    /** @return list<CandidateImportRowPreview> */
    public function rows(): array
    {
        return $this->validation->rows;
    }

    public function fileIdFor(int $recordNumber): ?int
    {
        return $this->fileIds[$recordNumber] ?? null;
    }

    /** @return list<CandidateImportStagedFile> */
    public function rejectedFiles(): array
    {
        return array_values(array_filter($this->files, fn (CandidateImportStagedFile $file): bool => ! $file->isUsable()));
    }

    /**
     * Counts the preview header shows for the upload side of the batch.
     *
     * @return array{files: int, attached: int, unreferenced: int, rejected: int}
     */
    public function summary(): array
    {
        return [
            'files' => count($this->files),
            'attached' => count($this->fileIds),
            'unreferenced' => count($this->unreferenced),
            'rejected' => count($this->rejectedFiles()),
        ];
    }

    /** @return list<array{code: string, field: string|null, severity: string, context: array<string, int|string|list<string>>}> */
    public function noticesToArray(): array
    {
        return array_map(fn (CandidateImportIssue $notice): array => $notice->toArray(), $this->notices);
    }
}
