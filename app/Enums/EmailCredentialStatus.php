<?php

namespace App\Enums;

enum EmailCredentialStatus: string
{
    case NotConfigured = 'not_configured';
    case PendingValidation = 'pending_validation';
    case Active = 'active';
    case Invalid = 'invalid';
}
