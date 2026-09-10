<?php

namespace App\Data;

/**
 * The outcome of reading one uploaded candidate CSV.
 *
 * Either the file was structurally sound, and `$records` holds its candidate
 * data records with their original numbering, or `$errors` explains why nothing
 * could be read from it. A reading is never half accepted: a broken header or a
 * malformed record makes every row after it unreliable, so the reader refuses
 * the file instead of importing the part it happened to understand.
 */
final readonly class CandidateImportCsvReading
{
    /**
     * @param  list<string>  $headers  Canonical header identifiers, in file order; empty when the header was refused.
     * @param  list<CandidateImportCsvRecord>  $records  Candidate data records, excluding ignored empty ones.
     * @param  int  $ignoredEmptyRecords  Records whose cells were all empty; counted, reported, never imported.
     * @param  int  $totalRecords  Logical records the file contains, header and ignored empty records included.
     * @param  list<CandidateImportCsvError>  $errors
     */
    public function __construct(
        public array $headers = [],
        public array $records = [],
        public int $ignoredEmptyRecords = 0,
        public int $totalRecords = 0,
        public array $errors = [],
    ) {}

    /** @param list<CandidateImportCsvError> $errors */
    public static function refused(array $errors): self
    {
        return new self(errors: $errors);
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /** Candidate data records that count against the row limit. */
    public function rowCount(): int
    {
        return count($this->records);
    }

    public function hasHeader(string $header): bool
    {
        return in_array($header, $this->headers, true);
    }

    /** @return list<array{code: string, record: int|null, column: string|null, context: array<string, int|string>}> */
    public function errorsToArray(): array
    {
        return array_map(fn (CandidateImportCsvError $error): array => $error->toArray(), $this->errors);
    }
}
