<?php

return [
    'navigation_label' => 'Scoring',
    'title' => 'Scoring',
    'subtitle' => 'Balance how candidates are scored in this workspace.',
    'eyebrow' => 'Scoring configuration',
    'heading' => 'Balance how candidates are scored',
    'description' => 'The AI fit analysis is the base score. A referral adds a bonus on top of it, capped at 100.',
    'weights_heading' => 'Current bonus',
    'weights_description' => 'Applied to every referred application scored in this workspace.',
    'fields' => [
        'referral_bonus' => 'Referral bonus',
    ],
    'update' => [
        'action' => 'Update bonus',
        'heading' => 'Update referral bonus',
        'description' => 'A whole number between 0 and 100. A 40% bonus turns an AI score of 80 into 100.',
        'bonus_helper' => 'Added on top of the AI score for referred candidates. The result never exceeds 100.',
        'save' => 'Save bonus',
    ],
    'notifications' => [
        'updated' => 'Referral bonus updated',
    ],
];
