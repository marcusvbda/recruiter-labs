<?php

namespace App\Exceptions;

use App\Models\CompanyInvitation;
use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

/**
 * A usable pending invitation already exists for that workspace and address.
 * Re-issuing it silently would hide the fact that the recipient already holds a
 * working link, so the caller is told to resend the invitation it carries.
 */
class WorkspaceInvitationAlreadyPending extends RuntimeException implements ShouldntReport
{
    private function __construct(
        public readonly CompanyInvitation $invitation,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function for(CompanyInvitation $invitation): self
    {
        return new self($invitation, __('team.errors.invitation_already_pending', [
            'email' => $invitation->email,
        ]));
    }
}
