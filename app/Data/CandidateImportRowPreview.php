<?php

namespace App\Data;

use App\Enums\CandidateImportIdentityAction;
use App\Enums\CandidateImportIssueCode;
use App\Enums\CandidateImportIssueSeverity;
use App\Enums\CandidateImportRowStatus;

/**
 * Everything validation concluded about one record, before anything is written.
 *
 * This is the whole preview of a row: the values that survived validation, why
 * the ones that did not were refused, which existing candidate the email points
 * at, which other records in the same file claim the same identity, and what
 * the workspace will refuse to overwrite. No candidate and no material exists
 * because of it — a preview that created rows in the pool would make "cancel"
 * a lie.
 *
 * `$recordNumber` is the file's own logical record number, carried unchanged
 * from the reader so every screen quotes the line the recruiter can find.
 */
final readonly class CandidateImportRowPreview
{
    /**
     * @param  list<CandidateImportIssue>  $issues
     * @param  list<CandidateImportDisclosure>  $disclosures
     * @param  list<int>  $duplicateRecordNumbers  The other records sharing this normalized email.
     * @param  array<string, string>  $raw  The cells exactly as the file supplied them.
     */
    public function __construct(
        public int $recordNumber,
        public CandidateImportFields $fields,
        public CandidateImportIdentityAction $identity,
        public array $issues = [],
        public array $disclosures = [],
        public ?int $existingCandidateId = null,
        public ?string $duplicateGroup = null,
        public array $duplicateRecordNumbers = [],
        public ?int $existingMaterialCount = null,
        public array $raw = [],
    ) {}

    /**
     * The same preview, with further issues appended.
     *
     * A later pass — the one that matches uploaded files to rows — learns things
     * about a record that field validation could not know on its own. It says so
     * by rebuilding the preview rather than mutating it, so a preview handed to
     * one reader is never rewritten under another.
     *
     * @param  list<CandidateImportIssue>  $issues
     */
    public function withIssues(array $issues): self
    {
        if ($issues === []) {
            return $this;
        }

        return new self(
            recordNumber: $this->recordNumber,
            fields: $this->fields,
            identity: $this->identity,
            issues: array_merge($this->issues, $issues),
            disclosures: $this->disclosures,
            existingCandidateId: $this->existingCandidateId,
            duplicateGroup: $this->duplicateGroup,
            duplicateRecordNumbers: $this->duplicateRecordNumbers,
            existingMaterialCount: $this->existingMaterialCount,
            raw: $this->raw,
        );
    }

    /** Refused as supplied: only a corrected file can make this row importable. */
    public function isBlocked(): bool
    {
        foreach ($this->issues as $issue) {
            if ($issue->isBlocking()) {
                return true;
            }
        }

        return false;
    }

    /** Understood, but waiting for a choice the product will not make on its own. */
    public function needsDecision(): bool
    {
        if ($this->isBlocked()) {
            return false;
        }

        foreach ($this->issues as $issue) {
            if ($issue->severity() === CandidateImportIssueSeverity::Decision) {
                return true;
            }
        }

        return false;
    }

    public function isImportable(): bool
    {
        return ! $this->isBlocked() && ! $this->needsDecision();
    }

    public function hasIssue(CandidateImportIssueCode $code): bool
    {
        foreach ($this->issues as $issue) {
            if ($issue->code === $code) {
                return true;
            }
        }

        return false;
    }

    /**
     * The status this row is persisted with.
     *
     * A row that still needs anything from a human is `NeedsReview`, whether
     * the file has to change or only a decision does; the two are told apart by
     * the severity of its issues, not by a second status.
     */
    public function status(): CandidateImportRowStatus
    {
        return $this->isImportable() ? CandidateImportRowStatus::Pending : CandidateImportRowStatus::NeedsReview;
    }

    /**
     * Is this row selected for import before the reviewer touches anything?
     *
     * Only a row that needs nothing from anybody. Duplicates in particular are
     * never pre-selected, even when their contents are identical: picking one
     * silently is exactly the decision the recruiter came to make.
     */
    public function isPreselected(): bool
    {
        return $this->isImportable();
    }

    /** @return list<array{code: string, field: string|null, severity: string, context: array<string, int|string|list<string>>}> */
    public function issuesToArray(): array
    {
        return array_map(fn (CandidateImportIssue $issue): array => $issue->toArray(), $this->issues);
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'fields' => $this->fields->toArray(),
            'raw' => $this->raw,
            'identity' => $this->identity->value,
            'existing_candidate_id' => $this->existingCandidateId,
            'existing_material_count' => $this->existingMaterialCount,
            'duplicate_group' => $this->duplicateGroup,
            'duplicate_record_numbers' => $this->duplicateRecordNumbers,
            'disclosures' => array_map(
                fn (CandidateImportDisclosure $disclosure): array => $disclosure->toArray(),
                $this->disclosures,
            ),
        ];
    }
}
