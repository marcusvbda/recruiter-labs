<?php

namespace App\Policies;

use App\Models\CompanyInvitation;
use App\Models\User;

class CompanyInvitationPolicy
{
    public function view(User $user, CompanyInvitation $invitation): bool
    {
        return $this->ownsInvitationWorkspace($user, $invitation);
    }

    public function resend(User $user, CompanyInvitation $invitation): bool
    {
        return $this->ownsInvitationWorkspace($user, $invitation);
    }

    public function revoke(User $user, CompanyInvitation $invitation): bool
    {
        return $this->ownsInvitationWorkspace($user, $invitation);
    }

    /**
     * Authorization is resolved against the invitation's own workspace, never
     * against the workspace the user happens to be browsing, so an invitation
     * belonging to another tenant can never be acted on.
     */
    private function ownsInvitationWorkspace(User $user, CompanyInvitation $invitation): bool
    {
        $company = $invitation->company;

        return $company !== null && $company->isOwner($user);
    }
}
