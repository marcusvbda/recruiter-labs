<?php

namespace App\Data;

use App\Enums\CandidateImportIdentityAction;
use App\Models\CandidateImportRow;

/**
 * The frozen instruction execution applies, read back as a typed value.
 *
 * Confirmation wrote this; execution reads this and nothing else. In
 * particular it never reads `payload`, which is preview data describing a pool
 * state that may have moved on, and never re-resolves the email, re-matches a
 * filename or recomputes a date. If the workspace no longer matches what is
 * written here, that is a fact for the row to report, not a licence to import
 * something the recruiter never approved.
 *
 * Reading is total: a manifest that is absent or malformed produces `null`
 * rather than a half-populated instruction, because a row whose instruction
 * cannot be read must not be executed on guesses.
 */
final readonly class CandidateImportManifest
{
    public function __construct(
        public int $recordNumber,
        public int $revision,
        public CandidateImportIdentityAction $identity,
        public ?int $candidateId,
        public ?string $name,
        public ?string $email,
        public ?string $normalizedEmail,
        public ?string $phone,
        public ?string $linkedinUrl,
        public string $sourceLabel,
        public bool $contactOnly,
        public ?int $fileId,
        public ?string $filename,
        public ?string $receivedOn,
        /** @var list<string> */
        public array $decisions = [],
    ) {}

    public static function fromRow(CandidateImportRow $row): ?self
    {
        return self::fromArray($row->manifest);
    }

    /** @param array<string, mixed>|null $stored */
    public static function fromArray(?array $stored): ?self
    {
        if ($stored === null || $stored === []) {
            return null;
        }

        $identity = CandidateImportIdentityAction::tryFrom(is_string($stored['identity'] ?? null) ? $stored['identity'] : '');
        $label = $stored['source_label'] ?? null;

        // Only the two actionable identities were ever written into a
        // manifest; anything else means the row was not importable, and an
        // unlabelled document could not be attributed to a source at all.
        if ($identity === null || ! is_string($label) || $label === ''
            || ! in_array($identity, [CandidateImportIdentityAction::Create, CandidateImportIdentityAction::Reuse], true)) {
            return null;
        }

        $material = is_array($stored['material'] ?? null) ? $stored['material'] : null;
        $text = static fn (string $key, ?array $from = null): ?string => is_string(($from ?? $stored)[$key] ?? null)
            && (($from ?? $stored)[$key] !== '') ? (string) (($from ?? $stored)[$key]) : null;

        $candidateId = is_numeric($stored['candidate_id'] ?? null) ? (int) $stored['candidate_id'] : null;

        // The pairing is a guarantee of the manifest writer, and execution
        // refuses to interpret a manifest that has lost it: "reuse nobody" and
        // "create this specific candidate" are both unexecutable.
        if (($identity === CandidateImportIdentityAction::Reuse) !== ($candidateId !== null)) {
            return null;
        }

        $fileId = $material !== null && is_numeric($material['file_id'] ?? null) ? (int) $material['file_id'] : null;

        return new self(
            recordNumber: is_numeric($stored['record_number'] ?? null) ? (int) $stored['record_number'] : 0,
            revision: is_numeric($stored['revision'] ?? null) ? (int) $stored['revision'] : 0,
            identity: $identity,
            candidateId: $candidateId,
            name: $text('name'),
            email: $text('email'),
            normalizedEmail: $text('normalized_email'),
            phone: $text('phone'),
            linkedinUrl: $text('linkedin_url'),
            sourceLabel: $label,
            contactOnly: (bool) ($stored['contact_only'] ?? false),
            fileId: $fileId,
            filename: $fileId === null ? null : $text('filename', $material),
            receivedOn: $fileId === null ? null : $text('received_on', $material),
            decisions: array_values(array_filter(
                is_array($stored['decisions'] ?? null) ? $stored['decisions'] : [],
                is_string(...),
            )),
        );
    }

    /** Whether this row promised to bring a document with it. */
    public function addsMaterial(): bool
    {
        return $this->fileId !== null && ! $this->contactOnly;
    }

    public function creates(): bool
    {
        return $this->identity === CandidateImportIdentityAction::Create;
    }
}
