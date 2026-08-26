<?php

namespace App\Exceptions;

use App\Models\CompanyInvitation;
use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

/**
 * The invitation exists but its current state has no resend meaning: a revoked
 * one must be re-issued deliberately, and an accepted one whose accepter is
 * still an active member has nothing left to deliver.
 */
class WorkspaceInvitationNotResendable extends RuntimeException implements ShouldntReport
{
    private function __construct(
        public readonly CompanyInvitation $invitation,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function revoked(CompanyInvitation $invitation): self
    {
        return new self($invitation, __('team.errors.invitation_revoked_cannot_resend'));
    }

    public static function alreadyAccepted(CompanyInvitation $invitation): self
    {
        return new self($invitation, __('team.errors.invitation_already_accepted', [
            'email' => $invitation->email,
        ]));
    }
}
