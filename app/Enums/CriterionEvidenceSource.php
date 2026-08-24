<?php

namespace App\Enums;

/**
 * Where a piece of supporting evidence for a criterion was found.
 *
 * Deliberately limited to what the candidate actually submitted: the product
 * does not verify claims against anything external, so there is no "verified"
 * or "reference" source to point at.
 */
enum CriterionEvidenceSource: string
{
    case Resume = 'resume';
    case CoverLetter = 'cover_letter';
    case ApplicationAnswer = 'application_answer';

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
