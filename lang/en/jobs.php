<?php

return [
    'label' => 'Job',
    'plural_label' => 'Jobs',
    'navigation_label' => 'Jobs',
    'fields' => [
        'name' => 'Name',
        'created_at' => 'Created at',
        'description' => 'Description',
        'starts_at' => 'Starts at',
        'ends_at' => 'Ends at',
        'campaign_expectation' => 'Campaign expectation',
        'campaign_expectation_helper' => 'A free-text prompt describing what success looks like for this campaign, e.g. "Expect to hire 4 senior developers meeting at least 80% of criteria by the campaign end date". Used later by AI to evaluate whether the campaign met its goal.',
    ],
    'sections' => [
        'details' => 'Job details',
        'campaign' => 'Campaign',
        'criteria' => 'Evaluation criteria',
    ],
    'criteria' => [
        'prompt' => 'Criterion prompt',
        'prompt_helper' => 'Describe what this criterion means to the evaluating agent (maximum 150 characters).',
        'weight' => 'Weight',
        'add' => 'Add criterion',
    ],
    'pipeline' => [
        'view' => 'Pipeline',
    ],
];
