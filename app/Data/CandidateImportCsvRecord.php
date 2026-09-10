<?php

namespace App\Data;

/**
 * One candidate data record read from an uploaded CSV.
 *
 * `$number` is the logical record number the file itself gives this row: the
 * header is record 1, a line break inside a quoted value does not start a new
 * record, and an entirely empty record still consumes its number. Preview,
 * execution and recovery reports all quote this number back to the recruiter,
 * so it has to describe the file on their disk, not this object's position in
 * a list.
 *
 * `$values` holds the cells exactly as the file supplied them, keyed by the
 * canonical header identifier, after any correction-file marker was removed and
 * before any field is judged. Nothing here is trimmed, formatted or validated;
 * that is the next stage's work.
 */
final readonly class CandidateImportCsvRecord
{
    /** @param array<string, string> $values */
    public function __construct(
        public int $number,
        public array $values,
    ) {}

    /** The raw cell for a header, or null when the file does not have that column. */
    public function value(string $header): ?string
    {
        return $this->values[$header] ?? null;
    }
}
