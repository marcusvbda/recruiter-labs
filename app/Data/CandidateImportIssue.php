<?php

namespace App\Data;

use App\Enums\CandidateImportIssueCode;
use App\Enums\CandidateImportIssueSeverity;

/**
 * One reason a row needs attention, as data.
 *
 * The code says what happened, the field says where, and the context carries
 * only facts a message may need to quote — a limit, a rejection reason, the
 * name already on file. No sentence is built here: the same issue is rendered
 * in the preview table and in an exported correction file, in the recruiter's
 * own language, long after the worker that produced it has exited.
 */
final readonly class CandidateImportIssue
{
    /** @param array<string, int|string|list<string>> $context */
    public function __construct(
        public CandidateImportIssueCode $code,
        /** Canonical header identifier the issue belongs to; null when it concerns the row as a whole. */
        public ?string $field = null,
        public array $context = [],
    ) {}

    public function severity(): CandidateImportIssueSeverity
    {
        return $this->code->severity();
    }

    public function isBlocking(): bool
    {
        return $this->code->isBlocking();
    }

    /** @return array{code: string, field: string|null, severity: string, context: array<string, int|string|list<string>>} */
    public function toArray(): array
    {
        return [
            'code' => $this->code->value,
            'field' => $this->field,
            'severity' => $this->severity()->value,
            'context' => $this->context,
        ];
    }
}
