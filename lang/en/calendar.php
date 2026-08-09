<?php

return [
    'navigation_label' => 'Calendar',
    'title' => 'Calendar',
    'subtitle' => 'Connect your calendar account for future recruitment scheduling features.',
    'eyebrow' => 'Calendar integration',
    'heading' => 'Connect your recruitment calendar',
    'description' => 'Authorize RecruiterLabs to access your calendar. This connection does not create interviews or calendar events yet.',
    'status' => [
        'connected' => 'Connected',
        'reauthorization_required' => 'Reconnect required',
        'disconnected' => 'Not connected',
    ],
    'google' => [
        'reauthorization_description' => ':plugin needs your authorization again before it can be used.',
    ],
    'details' => [
        'account_name' => 'Account name',
        'account_email' => 'Connected account',
        'connected_at' => 'Connected at',
    ],
    'actions' => [
        'connect' => 'Connect',
        'reconnect' => 'Reconnect',
        'disconnect' => 'Disconnect',
    ],
    'disconnect' => [
        'heading' => 'Disconnect :plugin?',
        'description' => 'RecruiterLabs will remove the stored authorization for this account.',
        'confirm' => 'Disconnect',
    ],
    'notifications' => [
        'disconnected' => ':plugin disconnected',
    ],
];
