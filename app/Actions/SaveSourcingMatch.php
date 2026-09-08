<?php

namespace App\Actions;

use App\Actions\Concerns\RecordsSourcingMatchDecision;
use App\Enums\SourcingMatchState;
use App\Exceptions\SourcingMatchException;
use App\Models\SourcingMatch;
use App\Models\User;

/**
 * A recruiter keeps a suggested candidate for this job.
 *
 * Saving is a shortlisting note and nothing else: it creates no Application,
 * contacts nobody, and leaves the job's pipeline untouched. Its only effect
 * beyond the recruiter's own list is that a later search refresh must preserve
 * it instead of resetting the candidate to a fresh suggestion.
 *
 * A dismissed match cannot be saved directly — see {@see RestoreSourcingMatch}.
 * Dismissal is an explicit human decision, and quietly reversing it from a
 * different button would make the two decisions interchangeable.
 */
class SaveSourcingMatch
{
    use RecordsSourcingMatchDecision;

    /**
     * @throws SourcingMatchException When the match was dismissed, or the
     *                                candidate has since entered the job.
     */
    public function handle(SourcingMatch $match, User $user): SourcingMatch
    {
        return $this->recordDecision(
            $match,
            $user,
            SourcingMatchState::Saved,
            function (SourcingMatch $lockedMatch): void {
                if ($lockedMatch->state === SourcingMatchState::Dismissed) {
                    throw SourcingMatchException::dismissedMustBeRestoredFirst();
                }

                // The candidate may have been added to the job — through sourcing
                // or any other path — while this list was on screen. The hiring
                // workflow is authoritative from that point on, and shortlisting
                // somebody already in the process would only be misleading.
                if ($lockedMatch->candidateAlreadyInJob()) {
                    throw SourcingMatchException::candidateAlreadyInJob();
                }
            },
        );
    }
}
