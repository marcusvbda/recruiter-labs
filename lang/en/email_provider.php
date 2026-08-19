<?php

return [
    'navigation_label' => 'Email Provider',
    'title' => 'Email Provider',
    'subtitle' => 'Configure the email providers used for recruitment sending.',
    'default_badge' => 'Default',
    'fields' => [
        'provider' => 'Provider',
        'api_key' => 'API key',
        'from_address' => 'Sender email address',
    ],
    'providers' => [
        'resend' => 'Resend',
        'gmail' => 'Gmail',
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
        'description' => 'The key is encrypted at rest and is never shown again in full. The sender address must be verified by the provider.',
        'save' => 'Save and validate',
    ],
    'remove' => [
        'heading' => 'Remove the :provider key?',
        'description' => 'Recruitment emails will stop sending through this provider until a new key is configured. This action does not affect historical usage.',
        'confirm' => 'Remove key',
    ],
    'empty' => [
        'heading' => 'Not configured yet',
        'description' => 'Add an API key and verified sender address to enable recruitment emails for this workspace.',
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
    'gmail' => [
        'reauthorization_description' => ':plugin needs your authorization again before it can send recruitment emails.',
        'default_uses_another_connection' => ':plugin is the workspace default through another recruiter\'s connected account.',
        'status' => [
            'connected' => 'Connected',
            'reauthorization_required' => 'Reconnect required',
            'disconnected' => 'Not connected',
        ],
        'details' => [
            'account_name' => 'Account name',
            'account_email' => 'Connected account',
            'connected_at' => 'Connected at',
        ],
        'actions' => [
            'connect' => 'Connect :plugin',
            'reconnect' => 'Reconnect',
            'disconnect' => 'Disconnect',
        ],
        'disconnect' => [
            'heading' => 'Disconnect :plugin?',
            'description' => 'RecruiterLabs will remove your stored authorization. If this connection is the workspace default, it will stop being used for recruitment email.',
            'confirm' => 'Disconnect',
        ],
        'notifications' => [
            'disconnected' => ':plugin disconnected',
        ],
    ],
];
