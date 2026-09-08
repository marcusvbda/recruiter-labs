<?php

namespace App\Actions;

use App\Actions\Concerns\RecordsSourcingMatchDecision;
use App\Enums\SourcingMatchState;
use App\Models\SourcingMatch;
use App\Models\User;

/**
 * A recruiter sets a suggested candidate aside for this job.
 *
 * Dismissal only hides the candidate from this job's active sourcing list. It
 * does not delete the candidate, does not reject or alter an Application, and —
 * because a match belongs to exactly one job — cannot reach the same person's
 * standing in any other job. It is not fed back into the model either: the
 * decision changes what this recruiter sees, not how candidates are scored.
 *
 * The record is kept rather than deleted so the decision survives a search
 * refresh and stays reversible through {@see RestoreSourcingMatch}.
 */
class DismissSourcingMatch
{
    use RecordsSourcingMatchDecision;

    public function handle(SourcingMatch $match, User $user): SourcingMatch
    {
        // Reachable from both Suggested and Saved: a recruiter is allowed to
        // change their mind about someone they kept earlier. Dismissing a
        // candidate who has since joined the job is allowed too — it clears a
        // stale suggestion from the sourcing list and, by construction here,
        // leaves their Application untouched.
        return $this->recordDecision($match, $user, SourcingMatchState::Dismissed);
    }
}
