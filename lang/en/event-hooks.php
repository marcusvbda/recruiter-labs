<?php

return [
    'label' => 'Event Hook',
    'plural_label' => 'Event Hooks',
    'navigation_label' => 'Event Hooks',
    'sections' => [
        'trigger' => 'Trigger',
        'action' => 'Action',
    ],
    'event_types' => [
        'application_submitted' => 'Application submitted',
        'status_changed' => 'Application status changed',
    ],
    'action_types' => [
        'send_email' => 'Send email',
    ],
    'fields' => [
        'event_type' => 'Event',
        'action_type' => 'Action',
        'email_template' => 'Email template',
        'automatable' => 'Linked to',
        'status' => 'Status',
        'all_option' => 'All :type',
        'is_active' => 'Active',
    ],
    'relation_manager' => [
        'title' => 'Event hooks',
    ],
];
