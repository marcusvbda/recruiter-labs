<?php

namespace App\Actions;

use App\Actions\Concerns\RecordsSourcingMatchDecision;
use App\Enums\SourcingMatchState;
use App\Exceptions\SourcingMatchException;
use App\Models\SourcingMatch;
use App\Models\User;

/**
 * A recruiter reconsiders a candidate they dismissed for this job.
 *
 * Restoring returns the match to {@see SourcingMatchState::Suggested} — the
 * state it would have had if nobody had decided anything — and not to Saved.
 * Dismissing and saving are two different decisions, and promoting a reversal
 * into an endorsement would record an opinion the recruiter never gave; if they
 * want the candidate shortlisted, saving is one deliberate click away.
 *
 * Only a dismissed match can be restored. There is nothing to undo on a
 * suggested one, and "restoring" a saved match would silently discard a decision
 * the recruiter did make.
 */
class RestoreSourcingMatch
{
    use RecordsSourcingMatchDecision;

    /**
     * @throws SourcingMatchException When the match is saved rather than dismissed.
     */
    public function handle(SourcingMatch $match, User $user): SourcingMatch
    {
        return $this->recordDecision(
            $match,
            $user,
            SourcingMatchState::Suggested,
            function (SourcingMatch $lockedMatch): void {
                if ($lockedMatch->state !== SourcingMatchState::Dismissed) {
                    throw SourcingMatchException::notDismissed();
                }
            },
        );
    }
}
