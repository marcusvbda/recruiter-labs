<?php

return [
    'label' => 'Referral',
    'plural_label' => 'Referrals',
    'navigation_label' => 'Referrals',
    'sections' => [
        'details' => 'Referral details',
        'availability' => 'Link availability',
        'availability_description' => 'Control when this referral link can receive applications.',
    ],
    'fields' => [
        'job' => 'Job',
        'user' => 'User',
        'published' => 'Published',
        'published_helper' => 'Only published referral links can be accessed.',
        'expires_at' => 'Valid until',
        'expires_at_helper' => 'Leave blank for no expiration date.',
        'max_applications' => 'Allowed applications',
        'max_applications_helper' => 'The link becomes unavailable when this limit is reached.',
        'created_at' => 'Created at',
    ],
];
