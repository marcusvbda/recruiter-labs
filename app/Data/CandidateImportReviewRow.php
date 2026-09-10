<?php

namespace App\Data;

use App\Enums\CandidateImportDecisionKind;
use App\Enums\CandidateImportIdentityAction;
use App\Enums\CandidateImportIssueCode;
use App\Enums\CandidateImportIssueSeverity;
use App\Enums\CandidateImportRowStatus;
use App\Models\CandidateImportRow;

/**
 * One persisted row as the review screen sees it: the preview plus the answer.
 *
 * Validation wrote what the file said and what the workspace thinks of it;
 * this reads that back beside the reviewer's decision and works out what the
 * row now means. Nothing is recomputed from the CSV here — the file may be
 * long gone from the request that uploaded it, and re-reading it would let two
 * screens disagree about the same record.
 *
 * The row is *eligible* when nothing is outstanding: every blocking problem is
 * either absent or answered by a decision that can answer it, and every
 * question the product refused to decide has been decided. An eligible row
 * that was not excluded is imported; anything else is counted and stated, not
 * quietly dropped.
 */
final readonly class CandidateImportReviewRow
{
    /**
     * @param  list<CandidateImportIssue>  $issues  Everything validation and association concluded.
     * @param  array<string, mixed>  $payload  The preview payload exactly as it was persisted.
     */
    public function __construct(
        public int $recordNumber,
        public array $issues = [],
        public ?CandidateImportDecision $decision = null,
        public ?int $fileId = null,
        public array $payload = [],
        public ?string $normalizedEmail = null,
        public bool $selected = false,
        public CandidateImportRowStatus $status = CandidateImportRowStatus::Pending,
    ) {}

    public static function fromModel(CandidateImportRow $row): self
    {
        /** @var array<string, mixed> $payload */
        $payload = is_array($row->payload) ? $row->payload : [];

        return new self(
            recordNumber: (int) $row->record_number,
            issues: self::issuesFrom($row),
            decision: CandidateImportDecision::fromArray($row->decision),
            fileId: $row->file_id !== null ? (int) $row->file_id : null,
            payload: $payload,
            normalizedEmail: $row->normalized_email,
            selected: (bool) $row->selected,
            status: $row->status,
        );
    }

    /** The same row with a different decision standing, without touching the database. */
    public function withDecision(?CandidateImportDecision $decision): self
    {
        return new self(
            recordNumber: $this->recordNumber,
            issues: $this->issues,
            decision: $decision,
            fileId: $this->fileId,
            payload: $this->payload,
            normalizedEmail: $this->normalizedEmail,
            selected: $this->selected,
            status: $this->status,
        );
    }

    /**
     * The problems the reviewer has not answered yet.
     *
     * Notices are never outstanding: they describe what the import will do,
     * not something it is waiting on.
     *
     * @return list<CandidateImportIssue>
     */
    public function outstanding(): array
    {
        $outstanding = [];

        foreach ($this->issues as $issue) {
            if ($issue->severity() === CandidateImportIssueSeverity::Notice) {
                continue;
            }
            if ($this->decision?->resolves($issue->code) === true) {
                continue;
            }
            $outstanding[] = $issue;
        }

        return $outstanding;
    }

    public function isEligible(): bool
    {
        return $this->outstanding() === [];
    }

    /** Still refused as supplied: only a corrected file can rescue it. */
    public function isBlocked(): bool
    {
        foreach ($this->outstanding() as $issue) {
            if ($issue->isBlocking()) {
                return true;
            }
        }

        return false;
    }

    /** Understood, and waiting for a choice the product will not make. */
    public function needsDecision(): bool
    {
        return ! $this->isBlocked() && $this->outstanding() !== [];
    }

    public function isExcluded(): bool
    {
        return $this->decision?->isExcluded() === true;
    }

    public function isContactOnly(): bool
    {
        return $this->decision?->has(CandidateImportDecisionKind::ContactOnly) === true;
    }

    /** Will this row be imported if the batch is confirmed as it stands? */
    public function willImport(): bool
    {
        return $this->isEligible() && ! $this->isExcluded();
    }

    /** Will a CV be added for this row? A contact-only row never adds one. */
    public function addsCv(): bool
    {
        return $this->willImport() && ! $this->isContactOnly() && $this->fileId !== null;
    }

    public function resolvedSelected(): bool
    {
        return $this->willImport();
    }

    public function resolvedStatus(): CandidateImportRowStatus
    {
        if ($this->isExcluded()) {
            return CandidateImportRowStatus::Excluded;
        }

        return $this->isEligible() ? CandidateImportRowStatus::Pending : CandidateImportRowStatus::NeedsReview;
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

    /** Does this row reference a CV file at all, whatever became of it? */
    public function referencesCv(): bool
    {
        return $this->cvFilename() !== null || $this->fileId !== null;
    }

    /** Does the row's file reference fail to name exactly one usable upload? */
    public function hasInvalidFileReference(): bool
    {
        foreach ($this->issues as $issue) {
            if ($issue->code->concernsCv() && $issue->code !== CandidateImportIssueCode::CvOmittedByReviewer) {
                return true;
            }
        }

        return false;
    }

    public function identity(): CandidateImportIdentityAction
    {
        $value = $this->payload['identity'] ?? null;

        return is_string($value)
            ? (CandidateImportIdentityAction::tryFrom($value) ?? CandidateImportIdentityAction::Unresolved)
            : CandidateImportIdentityAction::Unresolved;
    }

    public function existingCandidateId(): ?int
    {
        $value = $this->payload['existing_candidate_id'] ?? null;

        return is_int($value) ? $value : null;
    }

    public function existingMaterialCount(): int
    {
        $value = $this->payload['existing_material_count'] ?? null;

        return is_int($value) ? $value : 0;
    }

    public function duplicateGroup(): ?string
    {
        $value = $this->payload['duplicate_group'] ?? null;

        return is_string($value) ? $value : null;
    }

    /** @return array<string, string|null> */
    public function fields(): array
    {
        /** @var array<string, string|null> $fields */
        $fields = is_array($this->payload['fields'] ?? null) ? $this->payload['fields'] : [];

        return $fields;
    }

    public function field(string $name): ?string
    {
        $value = $this->fields()[$name] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function cvFilename(): ?string
    {
        return $this->field('cv_filename');
    }

    /** The received date, which a contact-only row no longer has. */
    public function receivedOn(): ?string
    {
        return $this->isContactOnly() ? null : $this->field('received_on');
    }

    /**
     * The snapshot execution will apply, and the only thing it may read.
     *
     * Written once, at confirmation, from the decision standing at that
     * moment. It states the identity, the document and the date as facts, so a
     * worker running an hour later cannot reach a different answer than the
     * screen the recruiter approved — not because the pool moved on, not
     * because a file was re-uploaded, not because the code that computed the
     * preview changed in the meantime.
     *
     * @return array{record_number: int, revision: int, identity: string, candidate_id: int|null, name: string|null, email: string|null, normalized_email: string|null, phone: string|null, linkedin_url: string|null, source_label: string, contact_only: bool, material: array{file_id: int, filename: string|null, received_on: string|null}|null, decisions: list<string>}
     */
    public function manifest(int $revision, string $batchSourceLabel): array
    {
        $decision = $this->decision;
        $reuseId = $this->existingCandidateId();
        $material = $this->addsCv() && $this->fileId !== null
            ? [
                'file_id' => $this->fileId,
                'filename' => $this->cvFilename(),
                'received_on' => $this->receivedOn(),
            ]
            : null;

        return [
            'record_number' => $this->recordNumber,
            'revision' => $revision,
            'identity' => $reuseId !== null
                ? CandidateImportIdentityAction::Reuse->value
                : CandidateImportIdentityAction::Create->value,
            'candidate_id' => $reuseId,
            'name' => $this->field('name'),
            'email' => $this->field('email'),
            'normalized_email' => $this->normalizedEmail,
            'phone' => $this->field('phone'),
            'linkedin_url' => $this->field('linkedin_url'),
            'source_label' => $this->field('source_label') ?? $batchSourceLabel,
            'contact_only' => $this->isContactOnly(),
            'material' => $material,
            'decisions' => $decision === null ? [] : array_map(
                fn (CandidateImportDecisionKind $kind): string => $kind->value,
                $decision->kinds,
            ),
        ];
    }

    /**
     * Everything the review screen shows about this row, decision included.
     *
     * @return list<array{code: string, field: string|null, severity: string, context: array<string, int|string|list<string>>}>
     */
    public function issuesToArray(): array
    {
        $issues = array_merge($this->issues, $this->decision?->notices() ?? []);

        return array_map(fn (CandidateImportIssue $issue): array => $issue->toArray(), $issues);
    }

    /**
     * Read back the issues validation and association wrote on the row.
     *
     * @return list<CandidateImportIssue>
     */
    private static function issuesFrom(CandidateImportRow $row): array
    {
        $stored = $row->issues;

        if (! is_array($stored)) {
            return [];
        }

        $issues = [];

        foreach ($stored as $entry) {
            if (! is_array($entry) || ! is_string($entry['code'] ?? null)) {
                continue;
            }
            $code = CandidateImportIssueCode::tryFrom($entry['code']);
            if ($code === null) {
                continue;
            }
            /** @var array<string, int|string|list<string>> $context */
            $context = is_array($entry['context'] ?? null) ? $entry['context'] : [];
            $issues[] = new CandidateImportIssue(
                $code,
                is_string($entry['field'] ?? null) ? $entry['field'] : null,
                $context,
            );
        }

        return $issues;
    }
}
