<?php

namespace App\Data;

/**
 * The aggregated candidate-supplied material that reaches the sourcing agent,
 * with direct candidate identifiers already removed.
 *
 * The candidate-level counterpart of {@see BlindCandidateContext}: same promise,
 * different scope. Where that one carries a single application's resume, cover
 * letter and answers, this one carries everything the candidate submitted across
 * their history, each item keeping its own source and submission date so
 * provenance and source age survive into the recruiter-facing output.
 *
 * This is not anonymisation. It removes identifiers that have no legitimate role
 * in judging fit — name, email, phone, social profiles — while deliberately
 * keeping employers, institutions, technologies, projects, titles, dates,
 * tenure, qualifications and numbers, which are the evidence. Indirect identity
 * may survive in the prose, and the product must never claim otherwise.
 *
 * Every item is a {@see CandidateSourcingMaterial}, so nothing that is not
 * candidate-submitted material — structured interview feedback above all — can
 * be represented here at all.
 */
final class BlindCandidateSourcingContext
{
    /**
     * @param  list<CandidateSourcingMaterial>  $materials  Redacted material, empty when the candidate submitted nothing usable.
     */
    public function __construct(
        public readonly array $materials,
        /** How many identifier occurrences were replaced, for tests and diagnostics. */
        public readonly int $redactionCount = 0,
    ) {}

    /**
     * Whether there is anything to evaluate at all. A candidate with only
     * identity and contact data leaves nothing behind once identifiers are gone,
     * and must be reported as insufficient information rather than scored.
     */
    public function isEmpty(): bool
    {
        return $this->materials === [];
    }
}
