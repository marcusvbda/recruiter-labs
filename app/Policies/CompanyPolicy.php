<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    /**
     * Membership alone is not authorization: a member whose workspace access is
     * disabled fails here too. This is also what
     * `ConnectedIntegrationTokenManager` authorizes against, so their
     * workspace-scoped credentials stop being usable for new actions in this
     * workspace while their access is disabled.
     */
    public function update(User $user, Company $company): bool
    {
        return $company->hasWorkspaceAccess($user);
    }

    /**
     * Seeing who is in the workspace is part of working in it, so every member
     * with access reads the Team area regardless of role. Someone who cannot
     * enter the workspace at all cannot read its team either.
     */
    public function viewTeam(User $user, Company $company): bool
    {
        return $company->hasWorkspaceAccess($user);
    }

    /**
     * Changing who belongs to the workspace, and who may currently enter it, is
     * an ownership decision: inviting, resending, revoking, removing and
     * enabling or disabling a member's workspace access are all gated on being
     * the workspace owner. The owner always has access, so no access check is
     * needed on top of this one.
     */
    public function manageTeam(User $user, Company $company): bool
    {
        return $company->isOwner($user);
    }
}
