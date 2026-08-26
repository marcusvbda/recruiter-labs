<?php

namespace App\Actions;

use App\Exceptions\WorkspaceOwnerCannotBeRemoved;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Removal deletes the membership row and nothing else. That row is the whole
 * authorization mechanism — `User::canAccessTenant()` and `CompanyPolicy` read
 * it directly — so everything the person authored stays where it is, still
 * attributed to them, and their account and other workspaces are untouched.
 */
class RemoveWorkspaceMember
{
    public function handle(Company $company, User $member, User $actor): void
    {
        // Authorization lives here, not in the Team screen, so a Member issuing
        // the request directly is refused too, and it resolves against this
        // workspace, so it cannot be driven from another tenant.
        Gate::forUser($actor)->authorize('manageTeam', $company);

        DB::transaction(function () use ($company, $member): void {
            if ($company->isOwner($member)) {
                throw WorkspaceOwnerCannotBeRemoved::for($company, $member);
            }

            // Removing someone who is no longer a member is the same outcome as
            // removing them once, so a second click or a stale list is harmless.
            // The invitation row is deliberately left alone: it records how this
            // person joined, and re-inviting them re-issues that same row with a
            // new token, which is what gives them a fresh valid invitation.
            $company->users()->detach($member->getKey());
        });
    }
}
