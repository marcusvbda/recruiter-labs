<?php

return [
    'plugins' => [
        'google_calendar' => [
            'label' => 'Google Calendar',
            'description' => 'Connect a Google account for calendar scheduling.',
            'category' => 'Calendars',
        ],
        'gmail' => [
            'label' => 'Gmail',
            'description' => 'Send recruitment email through a connected Gmail account.',
            'category' => 'Email',
        ],
    ],
    'notifications' => [
        'cancelled' => ':plugin authorization was cancelled or denied.',
        'connect_failed' => ':plugin could not be connected. Please try again.',
        'connected' => ':plugin connected successfully.',
        'disconnected' => ':plugin disconnected successfully.',
    ],
];
