<?php

namespace App\Enums;

/**
 * Where a piece of supporting evidence for a criterion was found.
 *
 * Deliberately limited to what the candidate actually submitted: the product
 * does not verify claims against anything external, so there is no "verified"
 * or "reference" source to point at.
 *
 * `candidate_material` is an independently retained candidate CV — one held
 * against the candidate in the workspace's pool rather than submitted with an
 * application. It is a separate case rather than a second meaning for `resume`
 * because the two make different claims: `resume` says the candidate attached
 * this document to an application on a date the product can point at, while
 * `candidate_material` says the workspace holds a CV whose historical receipt
 * date may be declared, may be unknown, and never came with an application at
 * all. Collapsing them would let stored evidence imply a submission that never
 * happened.
 */
enum CriterionEvidenceSource: string
{
    case Resume = 'resume';
    case CoverLetter = 'cover_letter';
    case ApplicationAnswer = 'application_answer';
    case CandidateMaterial = 'candidate_material';

    public function label(): string
    {
        return __('applications.admin.ai.evidence.sources.'.$this->value);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $source): string => $source->value, self::cases());
    }
}
