<?php

return [
    'label' => 'Pipeline',
    'plural_label' => 'Pipelines',
    'navigation_label' => 'Pipelines',
    'list_subheading' => 'A pipeline is a recruitment process: the stages a candidate moves through, and what each stage tells them.',
    'create_subheading' => 'Give the process a name. You can rename, reorder and remove stages afterwards.',
    'edit_subheading' => 'Rename the process, choose whether it is the default, and manage its stages below.',
    'empty_flow' => 'No stages yet',
    'sections' => [
        'details' => 'Pipeline details',
        'details_description' => 'How this recruitment process is identified across jobs.',
    ],
    'fields' => [
        'name' => 'Name',
        'description' => 'Description',
        'description_helper' => 'Optional. A short note about when this process should be used.',
        'is_default' => 'Default pipeline',
        'is_default_helper' => 'Preselected when creating a job. Only one pipeline can be the default.',
        'flow' => 'Stages',
        'jobs_count' => 'Jobs',
    ],
    'badges' => [
        'default' => 'Default',
        'secondary' => 'Available',
    ],
    'actions' => [
        'duplicate' => 'Duplicate',
        'duplicate_description' => 'Creates a copy of this pipeline with the same stages, colors and stage emails. Jobs and candidates are not copied, and the copy does not become the default.',
        'set_default' => 'Set as default',
    ],
    'notifications' => [
        'duplicated' => 'Pipeline duplicated as ":name".',
        'default_updated' => 'Default pipeline updated.',
        'pipeline_in_use_title' => "This pipeline can't be deleted",
    ],
    'default' => [
        'name' => 'Standard Recruitment',
        'description' => 'Default hiring process created with the company.',
    ],
    'duplicate' => [
        'name' => ':name - Copy',
    ],
    'variables' => [
        'samples' => [
            'candidate_name' => 'Alex Candidate',
            'job_title' => 'Senior Engineer',
            'company_name' => 'Your Company',
            'application_status' => 'Screening',
        ],
    ],
    'errors' => [
        'pipeline_in_use' => 'This pipeline is used by :count job(s), so it cannot be deleted. Move those jobs to another pipeline first.',
        'status_in_use' => 'This stage has :count candidate(s) in it, so it cannot be deleted. Move them to another stage first.',
        'pipeline_locked' => 'This job already has applications, so its pipeline can no longer be changed.',
        'cross_tenant_status' => 'That stage belongs to another company.',
        'cross_pipeline_status' => "That stage belongs to a different pipeline than this job's.",
        'missing_initial_status' => 'This pipeline has no stages yet, so applications cannot enter it.',
    ],
];
