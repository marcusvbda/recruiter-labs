<?php

namespace App\Enums;

enum AiConfigurationEventType: string
{
    case ProviderChanged = 'ai_provider_changed';
    case CredentialAdded = 'ai_credential_added';
    case CredentialReplaced = 'ai_credential_replaced';
    case CredentialRemoved = 'ai_credential_removed';
    case CredentialTestSucceeded = 'ai_credential_test_succeeded';
    case CredentialTestFailed = 'ai_credential_test_failed';
}
