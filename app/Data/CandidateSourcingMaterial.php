<?php

namespace App\Data;

use App\Enums\CriterionEvidenceSource;
use Carbon\CarbonImmutable;

/**
 * One piece of candidate-submitted material, gathered across the candidate's
 * history, that may be considered when sourcing them for another job.
 *
 * `$source` is a {@see CriterionEvidenceSource}, so the type itself limits this
 * to what the candidate wrote: a resume, a cover letter, an application answer,
 * or an independently retained candidate CV. There is deliberately no case for
 * structured interview feedback, recruiter notes or a previous AI evaluation —
 * none of them are candidate material, and none of them may reach a sourcing
 * agent.
 *
 * ## Dates
 *
 * Three dates exist because the product must not merge them into one confident
 * answer:
 *
 * - `$submittedAt` — when the candidate handed this material over. Known for
 *   application material (the application's submission, or a document's upload).
 *   **Null for an independent CV whose historical receipt was never declared**,
 *   and it stays null: the day someone imported a file is not evidence of when
 *   the candidate produced or sent it.
 * - `$receivedOn` — the recruiter-declared historical receipt date of an
 *   independent CV, when one was declared. It is the same value as
 *   `$submittedAt` for such a material, kept separately so a consumer can tell a
 *   declared date from an observed one.
 * - `$addedAt` — when the workspace started holding the material. Always known
 *   for an independent CV, and the honest answer to "how long have we had this?"
 *   when the receipt date is unknown. It is never presented as a submission date.
 *
 * `$receivedDateKnown` therefore survives into the recruiter-facing output: an
 * imported CV with no declared date must read as *original received date
 * unknown*, never as *received today*.
 *
 * `$materialId` names the independent CV a result was built from, so the
 * recruiter can open the actual source behind a suggestion. It is an internal
 * key only — the original filename, the source label, the batch and the
 * importer are deliberately absent from this type, because none of them are
 * candidate evidence and any of them could reveal identity or imply authority.
 */
final class CandidateSourcingMaterial
{
    public function __construct(
        public readonly CriterionEvidenceSource $source,
        public readonly string $text,
        /** The question snapshot for an application answer; null for free-form material. */
        public readonly ?string $label = null,
        /** When the candidate handed this material over; null when that is genuinely unknown. */
        public readonly ?CarbonImmutable $submittedAt = null,
        /** The independent CV this came from, for recruiter-facing provenance. */
        public readonly ?int $materialId = null,
        /** The recruiter-declared historical receipt date of an independent CV. */
        public readonly ?CarbonImmutable $receivedOn = null,
        /** When the workspace started holding an independent CV. */
        public readonly ?CarbonImmutable $addedAt = null,
        /**
         * Whether the material's own date is known rather than merely the date
         * the workspace received a copy. Application material is always dated;
         * an independent CV is dated only when a received date was declared.
         */
        public readonly bool $receivedDateKnown = true,
    ) {}

    public function withText(string $text, ?string $label): self
    {
        return new self(
            source: $this->source,
            text: $text,
            label: $label,
            submittedAt: $this->submittedAt,
            materialId: $this->materialId,
            receivedOn: $this->receivedOn,
            addedAt: $this->addedAt,
            receivedDateKnown: $this->receivedDateKnown,
        );
    }

    /**
     * This material, absorbing the provenance of another copy of the same
     * substantive content.
     *
     * Identical content held in two places is one piece of support, not two, so
     * one item survives and the other is dropped. The survivor keeps its own
     * identity — its source, its label and the material it points at — and takes
     * only the *earliest* dates the workspace can honestly claim for that
     * content. Two rules make that safe:
     *
     * - Removing duplication must never relabel old evidence as newly received.
     *   Importing a copy of a four-year-old CV today cannot move a date forward,
     *   because only an earlier date is ever adopted.
     * - An unknown date is not overwritten by a manufactured one. It is filled in
     *   only from a date the other copy genuinely has — an application really was
     *   submitted then — which is a fact about the content, not the import.
     */
    public function mergedWith(self $other): self
    {
        $submittedAt = $this->earliest($this->submittedAt, $other->submittedAt);

        return new self(
            source: $this->source,
            text: $this->text,
            label: $this->label,
            submittedAt: $submittedAt,
            materialId: $this->materialId,
            receivedOn: $this->earliest($this->receivedOn, $other->receivedOn),
            addedAt: $this->earliest($this->addedAt, $other->addedAt),
            // Known when either copy carries a real date for this content; the
            // uncertainty only survives while every copy is undated.
            receivedDateKnown: $submittedAt !== null
                && ($this->receivedDateKnown || $other->receivedDateKnown),
        );
    }

    private function earliest(?CarbonImmutable $first, ?CarbonImmutable $second): ?CarbonImmutable
    {
        if ($first === null || $second === null) {
            return $first ?? $second;
        }

        return $first->lte($second) ? $first : $second;
    }
}
