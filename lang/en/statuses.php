<?php

return [
    'label' => 'Status',
    'plural_label' => 'Statuses',
    'navigation_label' => 'Statuses',
    'sections' => [
        'details' => 'Status details',
    ],
    'fields' => [
        'name' => 'Name',
        'color' => 'Color',
        'is_hired' => 'Hired status',
        'is_hired_helper' => 'Applications in this status count as successful hires.',
    ],
    'notifications' => [
        'has_applications' => "This status has candidates in it and can't be deleted.",
    ],
];
