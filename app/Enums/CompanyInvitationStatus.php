<?php

namespace App\Enums;

enum CompanyInvitationStatus: string
{
    case Pending = 'pending';
    case Expired = 'expired';
    case Revoked = 'revoked';
    case Accepted = 'accepted';

    public function label(): string
    {
        return __('company.invitation_statuses.'.$this->value);
    }
}
