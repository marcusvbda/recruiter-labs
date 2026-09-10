<?php

namespace App\Data;

use App\Enums\CandidateImportIssueCode;

/**
 * The outcome of validating one read CSV against a destination workspace.
 *
 * Either the batch had a usable source label and every record was judged, and
 * `$rows` holds one preview per record in file order, or `$errors` explains why
 * validation could not be attempted at all. Row-level problems never land here:
 * a file where half the rows are wrong is still a perfectly valid preview, and
 * refusing it wholesale would hide the nine hundred rows that are fine.
 */
final readonly class CandidateImportValidation
{
    /**
     * @param  list<CandidateImportRowPreview>  $rows
     * @param  list<CandidateImportIssue>  $errors  Batch-level reasons validation was not attempted.
     */
    public function __construct(
        public array $rows = [],
        public array $errors = [],
    ) {}

    /** @param list<CandidateImportIssue> $errors */
    public static function refused(array $errors): self
    {
        return new self(errors: $errors);
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /** @return list<CandidateImportRowPreview> */
    public function importable(): array
    {
        return array_values(array_filter($this->rows, fn (CandidateImportRowPreview $row): bool => $row->isImportable()));
    }

    /** @return list<CandidateImportRowPreview> */
    public function blocked(): array
    {
        return array_values(array_filter($this->rows, fn (CandidateImportRowPreview $row): bool => $row->isBlocked()));
    }

    /** @return list<CandidateImportRowPreview> */
    public function needingDecision(): array
    {
        return array_values(array_filter($this->rows, fn (CandidateImportRowPreview $row): bool => $row->needsDecision()));
    }

    /**
     * Counts the preview header shows, and the batch summary stores.
     *
     * @return array{rows: int, importable: int, blocked: int, needs_decision: int, creates: int, reuses: int, duplicate_groups: int, with_cv_reference: int}
     */
    public function summary(): array
    {
        $creates = 0;
        $reuses = 0;
        $withCv = 0;
        $groups = [];

        foreach ($this->rows as $row) {
            if ($row->duplicateGroup !== null) {
                $groups[$row->duplicateGroup] = true;
            }
            if ($row->fields->cvFilename !== null) {
                $withCv++;
            }
            if ($row->isBlocked()) {
                continue;
            }
            if ($row->existingCandidateId !== null) {
                $reuses++;

                continue;
            }
            $creates++;
        }

        return [
            'rows' => count($this->rows),
            'importable' => count($this->importable()),
            'blocked' => count($this->blocked()),
            'needs_decision' => count($this->needingDecision()),
            'creates' => $creates,
            'reuses' => $reuses,
            'duplicate_groups' => count($groups),
            'with_cv_reference' => $withCv,
        ];
    }

    /**
     * How many rows carry each issue code, for the preview's filter chips.
     *
     * @return array<string, int>
     */
    public function issueCounts(): array
    {
        $counts = [];

        foreach ($this->rows as $row) {
            /** @var array<string, true> $seen */
            $seen = [];
            foreach ($row->issues as $issue) {
                if (isset($seen[$issue->code->value])) {
                    continue;
                }
                $seen[$issue->code->value] = true;
                $counts[$issue->code->value] = ($counts[$issue->code->value] ?? 0) + 1;
            }
        }

        return $counts;
    }

    public function countWithIssue(CandidateImportIssueCode $code): int
    {
        return $this->issueCounts()[$code->value] ?? 0;
    }

    /** @return list<array{code: string, field: string|null, severity: string, context: array<string, int|string|list<string>>}> */
    public function errorsToArray(): array
    {
        return array_map(fn (CandidateImportIssue $error): array => $error->toArray(), $this->errors);
    }
}
