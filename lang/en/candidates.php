<?php

return [
    'label' => 'Candidate',
    'plural_label' => 'Candidates',
    'navigation_label' => 'Candidates',
    'sections' => [
        'contact' => 'Contact information',
        'social_profiles' => 'Social profiles',
    ],
    'fields' => [
        'name' => 'Name',
        'email' => 'Email',
        'phone' => 'Phone',
        'phone_country' => 'Country code',
        'socials' => 'Social networks',
        'network' => 'Network',
        'account' => 'Account',
        'processes' => 'Processes',
        'current_processes' => 'Where they are',
        'created_at' => 'Created at',
    ],
    'view' => [
        'profile' => 'Contact details',
        'applications' => 'Applications',
        'applications_description' => 'Every process this person takes part in. Their stage always belongs to a specific job.',
        'applications_count' => 'Applications',
        'no_applications' => 'Not in any process yet',
        'and_more' => '+ :count more',
        'next_interview' => 'interview on :date',
        'fit' => 'Fit :score',
    ],
    'filters' => [
        'created_between' => 'Created between',
        'from' => 'From',
        'until' => 'Until',
    ],
    'networks' => [
        'instagram' => 'Instagram',
        'linkedin' => 'LinkedIn',
        'x' => 'X (Twitter)',
        'facebook' => 'Facebook',
        'tiktok' => 'TikTok',
        'whatsapp' => 'WhatsApp',
        'other' => 'Other',
    ],
];
