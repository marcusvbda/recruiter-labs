<?php

namespace App\Actions;

use App\Models\CompanyInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Revocation withdraws one invitation and nothing else: the recipient's account
 * and their memberships in other workspaces are untouched, as is every other
 * invitation.
 */
class RevokeWorkspaceInvitation
{
    public function handle(CompanyInvitation $invitation, User $actor): CompanyInvitation
    {
        Gate::forUser($actor)->authorize('revoke', $invitation);

        return DB::transaction(function () use ($invitation): CompanyInvitation {
            $locked = CompanyInvitation::query()
                ->whereKey($invitation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Revoking twice is the same outcome as revoking once, so the UI
            // never has to special-case a second click or a stale list.
            if ($locked->isRevoked()) {
                return $locked;
            }

            // The token is deliberately left in place. Acceptance is gated on the
            // invitation still being pending, so a revoked row can never produce
            // membership whatever its token is; keeping the row reachable by its
            // emailed URL is what lets the landing page explain that the
            // invitation is no longer valid instead of looking malformed.
            $locked->forceFill(['revoked_at' => now()])->save();

            return $locked;
        });
    }
}
