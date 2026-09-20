<?php

namespace App\Enums;

/**
 * Where a job's evaluation criteria stand.
 *
 * A successful extraction lands directly in {@see self::Completed}, the single
 * state in which candidate evaluation may run: preparing the criteria is the
 * system's work, and a recruiter's edits to them apply as soon as they are
 * saved. {@see self::AwaitingReview} is a legacy state, kept for revisions
 * stored before activation became automatic.
 */
enum JobCriteriaProcessingStatus: string
{
    case NotStarted = 'not_started';
    case Pending = 'pending';
    case Processing = 'processing';

    /** Legacy: criteria stored before a revision activated itself on success. */
    case AwaitingReview = 'awaiting_review';

    /** This revision is current: it governs candidate evaluation. */
    case Completed = 'completed';
    case Failed = 'failed';

    /** The platform allowance was exhausted before extraction could begin. */
    case PendingQuota = 'pending_quota';

    /** Whether criteria exist and can be read and edited. */
    public function hasCriteria(): bool
    {
        return $this === self::AwaitingReview || $this === self::Completed;
    }
}
