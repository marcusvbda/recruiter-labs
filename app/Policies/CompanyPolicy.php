<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function update(User $user, Company $company): bool
    {
        return $user->companies()->whereKey($company)->exists();
    }

    /**
     * Seeing who is in the workspace is part of working in it, so every active
     * member reads the Team area regardless of role.
     */
    public function viewTeam(User $user, Company $company): bool
    {
        return $company->roleFor($user) !== null;
    }

    /**
     * Changing who has access is an ownership decision: inviting, resending,
     * revoking and removing are all gated on being the workspace owner.
     */
    public function manageTeam(User $user, Company $company): bool
    {
        return $company->isOwner($user);
    }
}
