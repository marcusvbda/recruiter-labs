<?php

namespace App\Data;

use App\Enums\CandidateImportCsvErrorCode;

/**
 * One file-level reason an uploaded candidate CSV was refused.
 *
 * It carries a code and the facts needed to point at the problem — the logical
 * record, the column, the limit that was exceeded — and no sentence. The reader
 * runs outside a request as easily as inside one, so the wording belongs to
 * whichever interface reports the failure, in whichever language it speaks.
 */
final readonly class CandidateImportCsvError
{
    /** @param array<string, int|string> $context */
    public function __construct(
        public CandidateImportCsvErrorCode $code,
        /** Logical record number the problem was found in; null when it concerns the whole file. */
        public ?int $record = null,
        /** Header identifier, or the raw header text when it could not be mapped. */
        public ?string $column = null,
        /** Extra facts for the message, such as `limit`, `expected` or `found`. */
        public array $context = [],
    ) {}

    /** @return array{code: string, record: int|null, column: string|null, context: array<string, int|string>} */
    public function toArray(): array
    {
        return [
            'code' => $this->code->value,
            'record' => $this->record,
            'column' => $this->column,
            'context' => $this->context,
        ];
    }
}
