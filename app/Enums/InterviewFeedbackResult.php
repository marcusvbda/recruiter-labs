<?php

namespace App\Enums;

/**
 * What an interview established about one job criterion, as observed by a human.
 *
 * These four results are deliberately not a scale. There is no numeric value, no
 * ordering and no mapping onto a fit contribution, because any of those would
 * turn human interview evidence into an automatic recalculation of the AI
 * evaluation — exactly what the feature forbids. The only things the domain
 * needs to know about a result are the two predicates below.
 *
 * {@see NotAssessed} is the reason this enum has four cases instead of three:
 * "we never got to it" is unresolved uncertainty, not a negative observation,
 * and must never collapse into {@see NotConfirmed}, a low score or a failure.
 */
enum InterviewFeedbackResult: string
{
    case Confirmed = 'confirmed';
    case PartiallyConfirmed = 'partially_confirmed';
    case NotConfirmed = 'not_confirmed';
    case NotAssessed = 'not_assessed';

    /**
     * Whether the interviewer actually asserted something about the criterion.
     *
     * True for the three results that report an observation — including
     * {@see NotConfirmed}, which is a real (negative) finding. This is where an
     * evidence note carries meaning: it records what was observed, not merely
     * the conclusion drawn from it.
     */
    public function isAssertion(): bool
    {
        return $this !== self::NotAssessed;
    }

    /**
     * Whether the interview resolved the uncertainty it was meant to resolve.
     *
     * {@see Confirmed} and {@see NotConfirmed} settle the question either way;
     * {@see PartiallyConfirmed} leaves important uncertainty standing, and
     * {@see NotAssessed} leaves the question exactly where it was before the
     * interview.
     */
    public function resolvesUncertainty(): bool
    {
        return $this === self::Confirmed || $this === self::NotConfirmed;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
