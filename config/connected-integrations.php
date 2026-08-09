<?php

use App\Integrations\Gmail\GmailPlugin;
use App\Integrations\GoogleCalendar\GoogleCalendarPlugin;

return [
    'state_ttl' => (int) env('OAUTH_CONNECTION_STATE_TTL', 600),

    'plugins' => [
        GoogleCalendarPlugin::class,
        GmailPlugin::class,
    ],

    'google' => [
        'scopes' => [
            'openid',
            'email',
            'profile',
            'https://www.googleapis.com/auth/calendar.events',
            'https://www.googleapis.com/auth/calendar.events.freebusy',
        ],
    ],

    'gmail' => [
        'scopes' => [
            'openid',
            'email',
            'profile',
            'https://www.googleapis.com/auth/gmail.send',
        ],
    ],
];
