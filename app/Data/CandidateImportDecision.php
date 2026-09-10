<?php

namespace App\Data;

use App\Enums\CandidateImportDecisionKind;
use App\Enums\CandidateImportIssueCode;
use Carbon\CarbonImmutable;

/**
 * What a reviewer decided about one row, as data.
 *
 * A row can need more than one answer at once — the matched candidate's name
 * differs *and* the CV cannot be attached — so a decision holds the set of
 * choices made, not the last one clicked. Excluding a row replaces the set:
 * once the row is out, nothing else about it is worth deciding.
 *
 * The decision records who decided and when, because a confirmed import is a
 * record of a person's choices and the manifest built from it has to be
 * explainable months later. It never records a corrected value: the reviewer
 * chooses between what the file said, and does not restate it.
 */
final readonly class CandidateImportDecision
{
    /**
     * @param  list<CandidateImportDecisionKind>  $kinds  Every choice currently standing for this row.
     * @param  string|null  $duplicateGroup  The group this row was chosen from, or left out of.
     * @param  int|null  $candidateId  The existing candidate whose reuse was confirmed.
     */
    public function __construct(
        public array $kinds = [],
        public ?string $duplicateGroup = null,
        public ?int $candidateId = null,
        public ?int $decidedById = null,
        public ?CarbonImmutable $decidedAt = null,
    ) {}

    /**
     * The same decision with one more choice standing.
     *
     * Adding any choice other than exclusion un-excludes the row: a reviewer
     * who confirms the reuse of a row they had excluded plainly wants it back.
     */
    public function with(
        CandidateImportDecisionKind $kind,
        ?string $duplicateGroup = null,
        ?int $candidateId = null,
        ?int $decidedById = null,
    ): self {
        if ($kind === CandidateImportDecisionKind::Exclude) {
            return new self(
                kinds: [CandidateImportDecisionKind::Exclude],
                duplicateGroup: $duplicateGroup ?? $this->duplicateGroup,
                candidateId: $candidateId ?? $this->candidateId,
                decidedById: $decidedById ?? $this->decidedById,
                decidedAt: CarbonImmutable::now(),
            );
        }

        $kinds = array_values(array_filter(
            $this->kinds,
            fn (CandidateImportDecisionKind $held): bool => $held !== $kind
                && $held !== CandidateImportDecisionKind::Exclude,
        ));
        $kinds[] = $kind;

        return new self(
            kinds: $kinds,
            duplicateGroup: $duplicateGroup ?? $this->duplicateGroup,
            candidateId: $candidateId ?? $this->candidateId,
            decidedById: $decidedById ?? $this->decidedById,
            decidedAt: CarbonImmutable::now(),
        );
    }

    public function has(CandidateImportDecisionKind $kind): bool
    {
        return in_array($kind, $this->kinds, true);
    }

    public function isExcluded(): bool
    {
        return $this->has(CandidateImportDecisionKind::Exclude);
    }

    public function keepsCv(): bool
    {
        return ! $this->has(CandidateImportDecisionKind::ContactOnly);
    }

    /** Has the reviewer already answered this particular problem? */
    public function resolves(CandidateImportIssueCode $code): bool
    {
        foreach ($this->kinds as $kind) {
            if ($kind->resolves($code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * What the decision itself has to disclose, owned by no validation pass.
     *
     * @return list<CandidateImportIssue>
     */
    public function notices(): array
    {
        $notices = [];

        if ($this->has(CandidateImportDecisionKind::ContactOnly)) {
            $notices[] = new CandidateImportIssue(CandidateImportIssueCode::CvOmittedByReviewer, 'cv_filename');
        }

        if ($this->isExcluded()) {
            $notices[] = new CandidateImportIssue(
                $this->duplicateGroup !== null
                    ? CandidateImportIssueCode::DuplicateNotChosen
                    : CandidateImportIssueCode::ExcludedByReviewer,
            );
        }

        return $notices;
    }

    /** @return array{kinds: list<string>, duplicate_group: string|null, candidate_id: int|null, decided_by_id: int|null, decided_at: string|null} */
    public function toArray(): array
    {
        return [
            'kinds' => array_map(fn (CandidateImportDecisionKind $kind): string => $kind->value, $this->kinds),
            'duplicate_group' => $this->duplicateGroup,
            'candidate_id' => $this->candidateId,
            'decided_by_id' => $this->decidedById,
            'decided_at' => $this->decidedAt?->toIso8601String(),
        ];
    }

    /**
     * Read a decision back from the row it was written on.
     *
     * A stored choice whose name no longer exists is dropped rather than
     * guessed at: the reviewer is asked again, which is the only honest
     * outcome for a question the product can no longer read.
     *
     * @param  array<string, mixed>|null  $stored
     */
    public static function fromArray(?array $stored): ?self
    {
        if ($stored === null) {
            return null;
        }

        $kinds = [];

        foreach (is_array($stored['kinds'] ?? null) ? $stored['kinds'] : [] as $value) {
            if (! is_string($value)) {
                continue;
            }
            $kind = CandidateImportDecisionKind::tryFrom($value);
            if ($kind !== null && ! in_array($kind, $kinds, true)) {
                $kinds[] = $kind;
            }
        }

        if ($kinds === []) {
            return null;
        }

        $decidedAt = is_string($stored['decided_at'] ?? null) ? CarbonImmutable::parse($stored['decided_at']) : null;

        return new self(
            kinds: $kinds,
            duplicateGroup: is_string($stored['duplicate_group'] ?? null) ? $stored['duplicate_group'] : null,
            candidateId: is_int($stored['candidate_id'] ?? null) ? $stored['candidate_id'] : null,
            decidedById: is_int($stored['decided_by_id'] ?? null) ? $stored['decided_by_id'] : null,
            decidedAt: $decidedAt,
        );
    }
}
