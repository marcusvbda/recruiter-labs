<?php

namespace App\Exceptions;

use App\Models\CompanyInvitation;
use App\Models\User;
use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

/**
 * The authenticated account is not the invited identity. The message states
 * only which account is signed in — the invited address is deliberately left
 * out, because whoever holds a forwarded link is not entitled to learn who the
 * workspace invited.
 */
class WorkspaceInvitationEmailMismatch extends RuntimeException implements ShouldntReport
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
        return new self($invitation, $user, __('team.errors.invitation_email_mismatch', [
            'email' => $user->email,
        ]));
    }
}
