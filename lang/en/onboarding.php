<?php

return [
    'checklist' => [
        'heading' => 'Getting to your first evaluation',
        'setup_complete' => 'Setup complete',
        'progress' => ':done of :total steps completed',
        'optional_heading' => 'Optional setup',
        'optional_done' => 'Done',
        'optional_not_available' => 'Ask a workspace owner',
        'steps' => [
            'workspace_created' => [
                'title' => 'Workspace created',
                // No action to offer: this step is already done the moment the
                // workspace exists. The hint only exists so the checklist never
                // renders a raw key if this milestone is ever missing.
                'hint' => 'Your workspace is ready. The next steps prepare its first hiring process.',
            ],
            'create_first_job' => [
                'title' => 'Create your first job',
                'hint' => 'A job tells RecruiterLabs what you are hiring for, so it can start matching candidates against it.',
                'action' => 'Create job',
            ],
            'confirm_hiring_criteria' => [
                'title' => 'Confirm hiring criteria',
                'hint' => 'Confirmed criteria let RecruiterLabs evaluate every candidate consistently against what you are actually looking for.',
                'action' => 'Confirm criteria',
            ],
            'add_first_application' => [
                'title' => 'Add your first application',
                'hint' => 'RecruiterLabs needs a real candidate application before it can show you what an evaluation looks like.',
                'action' => 'Add application',
            ],
            'evaluate_first_application' => [
                'title' => 'Evaluate your first application',
                'hint' => 'This turns your hiring criteria and the candidate profile into an evidence-backed evaluation — the core value of RecruiterLabs.',
                'action' => 'Open evaluation',
            ],
        ],
        'optional' => [
            'invite_teammate' => [
                'title' => 'Invite a teammate',
                'action' => 'Invite teammate',
            ],
            'connect_calendar' => [
                'title' => 'Connect your calendar',
                'action' => 'Connect calendar',
            ],
            'connect_email' => [
                'title' => 'Connect your email',
                'action' => 'Connect email',
            ],
        ],
    ],
    'welcome' => [
        'heading' => 'Welcome to RecruiterLabs',
        'intro' => 'RecruiterLabs turns your hiring criteria into evidence-backed candidate evaluations. A few steps get you to your first one.',
        'progress' => ':done of :total steps completed',
        'next_step_label' => 'Next up',
        'get_started' => 'Get started',
        'continue_later' => 'Continue later',
    ],
    'launcher' => [
        'label' => 'Workspace setup',
        'progress' => ':done of :total steps completed',
        'view_checklist' => 'View checklist',
        'hide' => 'Hide',
    ],
];
