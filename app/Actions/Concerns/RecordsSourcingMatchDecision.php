<?php

namespace App\Actions\Concerns;

use App\Enums\SourcingMatchState;
use App\Models\SourcingMatch;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * The single write path for a recruiter's decision about a sourcing match.
 *
 * Every sourcing decision is the same operation — lock the match, check the
 * transition is legitimate, write `state`, `state_changed_at` and
 * `state_changed_by_id` — so it lives here once instead of being retyped in
 * three actions that could then drift apart.
 *
 * Nothing outside `sourcing_matches` is ever touched: a sourcing decision is a
 * shortlisting note, not a hiring decision, and must never create an
 * Application, move a pipeline stage, or reach an Interview.
 */
trait RecordsSourcingMatchDecision
{
    /**
     * @param  (Closure(SourcingMatch): void)|null  $guard  Runs against the locked
     *                                                      row — the only state a
     *                                                      concurrent decision
     *                                                      cannot have moved under
     *                                                      us — and throws when the
     *                                                      transition is invalid.
     */
    protected function recordDecision(
        SourcingMatch $match,
        User $user,
        SourcingMatchState $state,
        ?Closure $guard = null,
    ): SourcingMatch {
        return DB::transaction(function () use ($match, $user, $state, $guard): SourcingMatch {
            $lockedMatch = SourcingMatch::query()
                ->whereKey($match->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Already the recruiter's recorded decision. Returning untouched keeps
            // the original decision's timestamp and author, so a double click does
            // not rewrite who decided what and when.
            if ($lockedMatch->state === $state) {
                return $this->syncBack($match, $lockedMatch);
            }

            if ($guard instanceof Closure) {
                $guard($lockedMatch);
            }

            $lockedMatch->forceFill([
                'state' => $state,
                'state_changed_at' => now(),
                'state_changed_by_id' => $user->getKey(),
            ])->save();

            return $this->syncBack($match, $lockedMatch);
        });
    }

    /**
     * Hands the caller back the instance it passed in, carrying the state that is
     * actually stored, rather than a second object it would have to reconcile.
     */
    private function syncBack(SourcingMatch $match, SourcingMatch $lockedMatch): SourcingMatch
    {
        $match->setRawAttributes($lockedMatch->getAttributes(), true);

        return $match;
    }
}
