<?php

return [
    'navigation_label' => 'Email Provider',
    'title' => 'Email Provider',
    'subtitle' => 'Configure the email providers used for recruitment sending.',
    'eyebrow' => 'Email provider configuration',
    'heading' => 'Configure your recruitment email providers',
    'description' => 'This configures the providers used to send candidate communications and automations. It does not affect the system\'s own account emails.',
    'default_badge' => 'Default',
    'fields' => [
        'provider' => 'Provider',
        'api_key' => 'API key',
    ],
    'providers' => [
        'resend' => 'Resend',
    ],
    'status' => [
        'valid' => 'Validated',
        'invalid' => 'Needs attention',
        'untested' => 'Not tested yet',
        'not_configured' => 'Not configured',
        'last_validated' => 'Last validated :date',
        'never_validated' => 'Never validated',
    ],
    'configure' => [
        'heading' => 'Configure :provider',
        'description' => 'The key is encrypted at rest and is never shown again in full.',
        'save' => 'Save and validate',
    ],
    'remove' => [
        'heading' => 'Remove the :provider key?',
        'description' => 'Recruitment emails will stop sending through this provider until a new key is configured. This action does not affect historical usage.',
        'confirm' => 'Remove key',
    ],
    'empty' => [
        'heading' => 'Not configured yet',
        'description' => 'Add an API key to enable candidate communications and automations for this workspace.',
    ],
    'actions' => [
        'configure' => 'Configure',
        'replace' => 'Replace key',
        'test' => 'Test connection',
        'remove' => 'Remove key',
        'set_default' => 'Set as default',
    ],
    'notifications' => [
        'key_removed' => 'Email provider key removed',
        'default_updated' => 'Default email provider updated',
    ],
];
