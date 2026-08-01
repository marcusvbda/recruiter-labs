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
    ],
    'notifications' => [
        'has_applications' => "This status has candidates in it and can't be deleted.",
    ],
];
