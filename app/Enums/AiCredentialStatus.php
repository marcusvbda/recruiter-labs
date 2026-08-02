<?php

namespace App\Enums;

enum AiCredentialStatus: string
{
    case NotConfigured = 'not_configured';
    case PendingValidation = 'pending_validation';
    case Active = 'active';
    case Invalid = 'invalid';
}
