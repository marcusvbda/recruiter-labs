<?php

return [
    'navigation_label' => 'Scoring',
    'title' => 'Scoring',
    'subtitle' => 'Balance how candidates are scored in this workspace.',
    'eyebrow' => 'Scoring configuration',
    'heading' => 'Balance how candidates are scored',
    'description' => 'Fit Evaluation and referral contributions are blended to score applications in this workspace.',
    'weights_heading' => 'Current weights',
    'weights_description' => 'These weights are applied to every scored application in this workspace.',
    'fields' => [
        'fit_evaluation_weight' => 'Fit Evaluation weight',
        'referral_weight' => 'Referral weight',
    ],
    'update' => [
        'action' => 'Update weights',
        'heading' => 'Update scoring weights',
        'description' => 'Both weights must be whole numbers between 0 and 100 that add up to exactly 100.',
        'sum_helper' => 'Fit Evaluation and referral weights must sum to 100.',
        'save' => 'Save weights',
    ],
    'validation' => [
        'weights_must_sum' => 'Fit Evaluation and referral weights must sum to 100.',
    ],
    'notifications' => [
        'updated' => 'Scoring weights updated',
    ],
];
