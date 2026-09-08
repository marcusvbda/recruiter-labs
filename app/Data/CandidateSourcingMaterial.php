<?php

namespace App\Data;

use App\Enums\CriterionEvidenceSource;
use Carbon\CarbonImmutable;

/**
 * One piece of candidate-submitted material, gathered across the candidate's
 * history, that may be considered when sourcing them for another job.
 *
 * `$source` is a {@see CriterionEvidenceSource}, so the type itself limits this
 * to what the candidate wrote: a resume, a cover letter or an application
 * answer. There is deliberately no case for structured interview feedback,
 * recruiter notes or a previous AI evaluation — none of them are candidate
 * material, and none of them may reach a sourcing agent.
 *
 * `$submittedAt` is carried so the source age can be shown to the recruiter: a
 * resume from four years ago is weaker context than one from last month, and the
 * product should say which it read rather than hide the difference.
 */
final class CandidateSourcingMaterial
{
    public function __construct(
        public readonly CriterionEvidenceSource $source,
        public readonly string $text,
        /** The question snapshot for an application answer; null for free-form material. */
        public readonly ?string $label = null,
        public readonly ?CarbonImmutable $submittedAt = null,
    ) {}

    public function withText(string $text, ?string $label): self
    {
        return new self(
            source: $this->source,
            text: $text,
            label: $label,
            submittedAt: $this->submittedAt,
        );
    }
}
