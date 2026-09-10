<?php

namespace App\Data;

/**
 * Exactly what confirming this import would do, counted.
 *
 * Every number here is a count of rows or of files, computed from the batch's
 * own persisted rows and their decisions — never estimated, never rounded, and
 * never a percentage. The confirmation screen is the last moment before a
 * workspace's candidate pool changes, and a recruiter who is told "about 80
 * candidates" has not been told anything they can be held to.
 *
 * Two identities hold always: `willImport` is `creates` plus `reuses`, and
 * `willNotImport` is every other row of the file. The reason buckets —
 * excluded, invalid, needing identity review, invalid file references — are
 * counted per reason and may overlap, because one row can carry two unanswered
 * questions and hiding the second one would send the recruiter back twice.
 */
final readonly class CandidateImportSummary
{
    public function __construct(
        /** Logical data records the file contained, ignored empty ones excluded. */
        public int $rows = 0,
        /** Records whose cells were all empty; read, counted, never imported. */
        public int $ignoredEmptyRecords = 0,
        /** Rows that will create a candidate the workspace does not have. */
        public int $creates = 0,
        /** Rows that will attach to a candidate the workspace already holds. */
        public int $reuses = 0,
        /** Rows still waiting on a decision about who they are. */
        public int $needsIdentityReview = 0,
        /** Rows refused as supplied; only a corrected file can rescue them. */
        public int $invalid = 0,
        /** Rows the reviewer deliberately left out. */
        public int $excluded = 0,
        /** Rows that will be imported without the CV they referenced. */
        public int $contactOnly = 0,
        /** CVs that will be added to the pool. */
        public int $cvsToAdd = 0,
        /** CVs the matched candidates already hold, which the import will not touch. */
        public int $alreadyRetainedCvs = 0,
        /** Rows whose CV reference does not name exactly one usable upload. */
        public int $invalidFileReferences = 0,
        /** Uploads no imported row will use. */
        public int $unusedFiles = 0,
    ) {}

    /** Rows the confirmation will actually import. */
    public function willImport(): int
    {
        return $this->creates + $this->reuses;
    }

    /** Rows that will not be imported, for whatever reason. */
    public function willNotImport(): int
    {
        return max(0, $this->rows - $this->willImport());
    }

    public function hasSelection(): bool
    {
        return $this->willImport() > 0;
    }

    /**
     * The sentence the final action states, in full and without hedging.
     *
     * It is built here rather than in a view because it is the claim the
     * product is accountable for: what it says is what the manifest holds.
     */
    public function statement(): string
    {
        return sprintf(
            'Import %d selected rows: create %d candidates, reuse %d candidates. %d rows will not be imported. %d CVs will be added.',
            $this->willImport(),
            $this->creates,
            $this->reuses,
            $this->willNotImport(),
            $this->cvsToAdd,
        );
    }

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return [
            'rows' => $this->rows,
            'ignored_empty_records' => $this->ignoredEmptyRecords,
            'will_import' => $this->willImport(),
            'creates' => $this->creates,
            'reuses' => $this->reuses,
            'needs_identity_review' => $this->needsIdentityReview,
            'invalid' => $this->invalid,
            'excluded' => $this->excluded,
            'contact_only' => $this->contactOnly,
            'cvs_to_add' => $this->cvsToAdd,
            'already_retained_cvs' => $this->alreadyRetainedCvs,
            'invalid_file_references' => $this->invalidFileReferences,
            'unused_files' => $this->unusedFiles,
            'will_not_import' => $this->willNotImport(),
            'statement' => $this->statement(),
        ];
    }

    /** @param array<string, mixed>|null $stored */
    public static function fromArray(?array $stored): self
    {
        $stored ??= [];
        $read = static fn (string $key): int => is_numeric($stored[$key] ?? null) ? (int) $stored[$key] : 0;

        return new self(
            rows: $read('rows'),
            ignoredEmptyRecords: $read('ignored_empty_records'),
            creates: $read('creates'),
            reuses: $read('reuses'),
            needsIdentityReview: $read('needs_identity_review'),
            invalid: $read('invalid'),
            excluded: $read('excluded'),
            contactOnly: $read('contact_only'),
            cvsToAdd: $read('cvs_to_add'),
            alreadyRetainedCvs: $read('already_retained_cvs'),
            invalidFileReferences: $read('invalid_file_references'),
            unusedFiles: $read('unused_files'),
        );
    }

    /** The same counts, with the number of empty records the reader ignored carried in. */
    public function withIgnoredEmptyRecords(int $ignored): self
    {
        return new self(
            rows: $this->rows,
            ignoredEmptyRecords: $ignored,
            creates: $this->creates,
            reuses: $this->reuses,
            needsIdentityReview: $this->needsIdentityReview,
            invalid: $this->invalid,
            excluded: $this->excluded,
            contactOnly: $this->contactOnly,
            cvsToAdd: $this->cvsToAdd,
            alreadyRetainedCvs: $this->alreadyRetainedCvs,
            invalidFileReferences: $this->invalidFileReferences,
            unusedFiles: $this->unusedFiles,
        );
    }
}
