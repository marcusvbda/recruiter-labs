<?php

namespace App\Enums;

use App\Models\SourcingMatch;

/**
 * What a recruiter has decided about a suggested match.
 *
 * These are the recruiter's own shortlisting notes and nothing more: they never
 * move a candidate through the hiring pipeline, never create an Application and
 * never contact anyone.
 *
 * There is deliberately no `AddedToJob` case. Whether the candidate is already
 * in the job is computed from `applications`
 * ({@see SourcingMatch::candidateAlreadyInJob()}), so a candidate
 * who arrived through any other path is recognised without a flag that several
 * write paths would have to keep in sync.
 */
enum SourcingMatchState: string
{
    /** Produced by a search, not yet acted on. */
    case Suggested = 'suggested';

    /** Kept for consideration by a recruiter. */
    case Saved = 'saved';

    /** Set aside by a recruiter; restorable, never deleted. */
    case Dismissed = 'dismissed';
}
