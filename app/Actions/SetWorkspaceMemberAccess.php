<?php

namespace App\Actions;

use App\Enums\CompanyRole;
use App\Exceptions\WorkspaceMemberNotFound;
use App\Exceptions\WorkspaceOwnerAccessCannotBeChanged;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Enabling and disabling a member's workspace access. It writes one column of
 * one `company_user` row and nothing else: the membership, the role, the team
 * listing and everything the person ever authored stay exactly as they are, and
 * only their current authorization changes.
 *
 * Deliberately separate from {@see RemoveWorkspaceMember}: disabling access keeps
 * someone on the team and is reversible here, removing ends their membership and
 * needs a new invitation. The two must never be conflated.
 */
class SetWorkspaceMemberAccess
{
    public function handle(Company $company, User $member, User $actor, bool $enabled): void
    {
        // Authorization lives here, not in the Team screen, so a member issuing
        // the request directly is refused too, and it resolves against this
        // workspace, so it cannot be driven from another tenant.
        Gate::forUser($actor)->authorize('manageTeam', $company);

        DB::transaction(function () use ($company, $member, $enabled): void {
            $membership = DB::table('company_user')
                ->where('company_id', $company->getKey())
                ->where('user_id', $member->getKey())
                ->lockForUpdate()
                ->first(['role', 'access_disabled_at']);

            if ($membership === null) {
                throw WorkspaceMemberNotFound::for($company, $member);
            }

            if ($membership->role === CompanyRole::Owner->value) {
                throw WorkspaceOwnerAccessCannotBeChanged::for($company, $member);
            }

            // Asking for the state the membership is already in is the same
            // outcome as asking for it once, so a second click or a stale team
            // list is harmless and writes nothing.
            if (($membership->access_disabled_at === null) === $enabled) {
                return;
            }

            $company->users()->updateExistingPivot($member->getKey(), [
                'access_disabled_at' => $enabled ? null : now(),
            ]);
        });
    }
}
