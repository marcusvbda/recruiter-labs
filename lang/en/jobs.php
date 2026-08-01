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
        'published' => 'Published',
        'campaign_expectation' => 'Campaign expectation',
        'campaign_expectation_helper' => 'A free-text prompt describing what success looks like for this campaign, e.g. "Expect to hire 4 senior developers meeting at least 80% of criteria by the campaign end date". Used later by AI to evaluate whether the campaign met its goal.',
    ],
    'sections' => [
        'details' => 'Job details',
        'application' => 'Application page',
        'campaign' => 'Campaign',
        'criteria' => 'Evaluation criteria',
    ],
    'application' => [
        'section_description' => 'Configure the information and CV formats requested from candidates on the application page.',
        'accepted_cv_types' => 'Accepted CV formats',
        'accepted_cv_types_helper' => 'Choose one or more formats. Use Select all to accept PDF, DOC, and DOCX.',
        'questions' => 'Application questions',
        'question' => 'Question',
        'response_type' => 'Response field type',
        'required' => 'Required answer',
        'field_description' => 'Field description',
        'field_description_helper' => 'Optional guidance displayed below the field for the candidate.',
        'add_question' => 'Add question',
        'question_types' => [
            'text' => 'Text input',
            'number' => 'Number input',
            'textarea' => 'Textarea',
        ],
        'cv_types' => [
            'pdf' => 'PDF',
            'doc' => 'DOC',
            'docx' => 'DOCX',
        ],
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
