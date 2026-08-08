<?php

namespace App\Enums;

enum EmailProviderConfigurationEventType: string
{
    case DefaultProviderChanged = 'email_default_provider_changed';
    case CredentialAdded = 'email_credential_added';
    case CredentialReplaced = 'email_credential_replaced';
    case CredentialRemoved = 'email_credential_removed';
    case CredentialTestSucceeded = 'email_credential_test_succeeded';
    case CredentialTestFailed = 'email_credential_test_failed';
}
