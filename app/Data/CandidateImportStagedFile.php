<?php

namespace App\Data;

use App\Enums\CandidateImportIssueCode;
use App\Models\CandidateImportFile;

/**
 * One uploaded file as the association pass sees it.
 *
 * Staging keeps the bytes under a generated name, so the only thing left that
 * can tie an upload to a row is `$associationName`: the client filename with
 * its Unicode representation normalized and its outer whitespace removed, kept
 * purely as a matching key. It is never a path, never a display name for the
 * candidate, and never compared loosely — two names match when they are the
 * same string, letter case included.
 *
 * A file that failed inspection is still described here, with the reasons it
 * failed, because the rows asking for it have to be told why they cannot have
 * it. Nothing on this object is a candidate material: staged files belong to
 * the batch and die with it.
 */
final readonly class CandidateImportStagedFile
{
    /** @param list<CandidateImportIssue> $issues */
    public function __construct(
        public int $id,
        public string $associationName,
        public ?string $checksum = null,
        public ?string $extension = null,
        public ?string $mimeType = null,
        public int $size = 0,
        public array $issues = [],
    ) {}

    public static function fromModel(CandidateImportFile $file): self
    {
        return new self(
            id: (int) $file->getKey(),
            associationName: (string) ($file->association_name ?? $file->original_name ?? ''),
            checksum: $file->checksum,
            extension: $file->extension,
            mimeType: $file->mime_type,
            size: (int) $file->size,
            issues: self::issuesFrom($file),
        );
    }

    /** Did this file pass inspection, so a row may legitimately be attached to it? */
    public function isUsable(): bool
    {
        return $this->issues === [] && $this->checksum !== null;
    }

    /** The first refusal reason, which is the one a referencing row quotes. */
    public function rejection(): ?CandidateImportIssue
    {
        return $this->issues[0] ?? null;
    }

    /**
     * Read back the refusals staging wrote on the model.
     *
     * @return list<CandidateImportIssue>
     */
    private static function issuesFrom(CandidateImportFile $file): array
    {
        $stored = $file->errors['issues'] ?? null;

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
            $field = is_string($entry['field'] ?? null) ? $entry['field'] : null;
            $issues[] = new CandidateImportIssue($code, $field, $context);
        }

        return $issues;
    }
}
