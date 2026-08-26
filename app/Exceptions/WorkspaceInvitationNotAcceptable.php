<?php

namespace App\Exceptions;

use App\Models\CompanyInvitation;
use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

/**
 * The invitation was found but its current state can no longer create
 * membership. It is a product outcome the landing page explains, not a fault,
 * and it carries the invitation so the screen can offer the right next step.
 */
class WorkspaceInvitationNotAcceptable extends RuntimeException implements ShouldntReport
{
    private function __construct(
        public readonly CompanyInvitation $invitation,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function expired(CompanyInvitation $invitation): self
    {
        return new self($invitation, __('team.errors.invitation_expired_cannot_accept'));
    }

    public static function revoked(CompanyInvitation $invitation): self
    {
        return new self($invitation, __('team.errors.invitation_revoked_cannot_accept'));
    }

    public static function alreadyAccepted(CompanyInvitation $invitation): self
    {
        return new self($invitation, __('team.errors.invitation_already_used'));
    }
}
