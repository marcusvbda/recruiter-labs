<?php

return [
    'label' => 'Application',
    'plural_label' => 'Applications',
    'pipeline' => [
        'title' => 'Pipeline: :job',
        'view_kanban' => 'Kanban view',
        'view_list' => 'List view',
        'add_candidate' => 'Add candidate',
        'no_candidates' => 'No candidates in this column yet.',
        'select_candidate' => 'Candidate',
        'candidate_added' => 'Candidate added to the pipeline.',
        'no_eligible_candidates' => 'All candidates have already been added to this pipeline.',
        'no_statuses' => 'This company has no statuses configured yet, so a candidate cannot be added to the pipeline.',
        'already_added' => 'This candidate has already been added to this pipeline.',
    ],
    'fields' => [
        'candidate' => 'Candidate',
        'status' => 'Status',
        'created_at' => 'Applied at',
    ],
];
