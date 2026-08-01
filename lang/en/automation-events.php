<?php

return [
    'label' => 'Automation Event',
    'plural_label' => 'Automation Events',
    'navigation_label' => 'Automation Events',
    'event_types' => [
        'application_submitted' => 'Application submitted',
        'status_changed' => 'Status changed',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ],
    'action_types' => [
        'send_email' => 'Send email',
    ],
    'fields' => [
        'event_type' => 'Event',
        'action_type' => 'Action',
        'email_template' => 'Email template',
        'automatable' => 'Linked to',
        'is_active' => 'Active',
    ],
    'relation_manager' => [
        'title' => 'Automation events',
    ],
];
