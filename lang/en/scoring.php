<?php

return [
    'navigation_label' => 'Scoring',
    'title' => 'Scoring',
    'subtitle' => 'Balance how candidates are scored in this workspace.',
    'eyebrow' => 'Scoring configuration',
    'heading' => 'Balance how candidates are scored',
    'description' => 'Control how much weight the AI fit analysis and the referral bonus carry when ranking applications.',
    'weights_heading' => 'Current weights',
    'weights_description' => 'These weights are applied to every application scored in this workspace.',
    'fields' => [
        'analysis_weight' => 'Analysis weight',
        'referral_weight' => 'Referral weight',
    ],
    'update' => [
        'action' => 'Update weights',
        'heading' => 'Update scoring weights',
        'description' => 'Both weights must be whole numbers between 0 and 100 that add up to exactly 100.',
        'sum_helper' => 'The analysis and referral weights must sum to 100.',
        'save' => 'Save weights',
    ],
    'notifications' => [
        'updated' => 'Scoring weights updated',
    ],
];
