<?php

namespace App\Data;

/**
 * The candidate-supplied material that reaches the evaluation agent, with direct
 * candidate identifiers already removed.
 *
 * This is not anonymisation. It removes identifiers that have no legitimate role
 * in judging fit — name, email, phone, social profiles — while deliberately
 * keeping everything a recruiter would actually evaluate: employers,
 * institutions, technologies, projects, titles, dates, tenure, qualifications
 * and numbers. Indirect identity may survive in the prose, and the product must
 * never claim otherwise.
 *
 * Candidate identity stays fully visible to the human recruiter everywhere else;
 * only this payload is identity-reduced.
 */
final class BlindCandidateContext
{
    /**
     * @param  list<array{question: string, answer: string|null}>  $answers
     */
    public function __construct(
        public readonly ?string $resumeText,
        public readonly ?string $coverLetter,
        public readonly array $answers,
        /** How many identifier occurrences were replaced, for tests and diagnostics. */
        public readonly int $redactionCount = 0,
    ) {}
}
