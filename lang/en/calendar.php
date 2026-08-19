<?php

return [
    'navigation_label' => 'Calendar',
    'integration_navigation_label' => 'Calendar integration',
    'title' => 'Calendar',
    'subtitle' => 'Connect your calendar account to schedule interviews and sync candidate responses.',
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
    'event' => [
        'summary' => 'Interview: :job',
        'description' => "Candidate: :candidate\nRole: :job",
    ],
];
