<?php

namespace App\Data;

use App\Models\Candidate;

/**
 * Everything the destination workspace has to say about the emails in one file.
 *
 * Read once, for the whole batch, so that a thousand-row preview does not run a
 * thousand lookups. It answers three questions and no others: how many
 * candidates carry an email, which candidate that is when there is exactly one,
 * and how many retained materials that candidate already holds.
 *
 * It deliberately cannot answer "who looks like this person": similarity is not
 * an identity key here, and a structure that could not be asked for it will not
 * be asked for it by mistake.
 */
final readonly class CandidateImportIdentityLookup
{
    /**
     * @param  array<string, list<Candidate>>  $candidates  Existing candidates keyed by normalized email.
     * @param  array<int, int>  $materialCounts  Retained material count keyed by candidate id.
     */
    public function __construct(
        public array $candidates = [],
        public array $materialCounts = [],
    ) {}

    public function matchCount(string $normalizedEmail): int
    {
        return count($this->candidates[$normalizedEmail] ?? []);
    }

    /** The single candidate carrying this email, or null when there is none or more than one. */
    public function single(string $normalizedEmail): ?Candidate
    {
        $matches = $this->candidates[$normalizedEmail] ?? [];

        return count($matches) === 1 ? $matches[0] : null;
    }

    /** How many retained independent CVs a candidate already holds, archived ones included. */
    public function materialCount(int $candidateId): int
    {
        return $this->materialCounts[$candidateId] ?? 0;
    }
}
