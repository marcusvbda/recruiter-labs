<?php

namespace App\Exceptions;

use App\Models\CompanyInvitation;
use App\Models\User;
use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

/**
 * The right account opened the invitation, but its email identity is not
 * verified yet. Membership stays uncreated until it is; the invitation itself
 * remains pending, so the same link still works after verification.
 */
class WorkspaceInvitationEmailNotVerified extends RuntimeException implements ShouldntReport
{
    private function __construct(
        public readonly CompanyInvitation $invitation,
        public readonly User $user,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function for(CompanyInvitation $invitation, User $user): self
    {
        return new self($invitation, $user, __('team.errors.invitation_email_not_verified', [
            'email' => $user->email,
        ]));
    }
}
