<?php

namespace App\Enums;

/**
 * Where a job's evaluation criteria stand.
 *
 * The AI never owns the criteria that govern candidate evaluation: extraction
 * finishing is *not* the same thing as the criteria being approved. A finished
 * extraction lands in {@see self::AwaitingReview}, and only a recruiter's
 * explicit confirmation moves it to {@see self::Completed}, which is the single
 * state in which candidate evaluation may run.
 */
enum JobCriteriaProcessingStatus: string
{
    case NotStarted = 'not_started';
    case Pending = 'pending';
    case Processing = 'processing';

    /** Criteria are stored and editable, but no human has confirmed them yet. */
    case AwaitingReview = 'awaiting_review';

    /** A recruiter confirmed this revision: it governs candidate evaluation. */
    case Completed = 'completed';
    case Failed = 'failed';

    /** Whether criteria exist and can be read, edited and confirmed. */
    public function hasCriteria(): bool
    {
        return $this === self::AwaitingReview || $this === self::Completed;
    }
}
