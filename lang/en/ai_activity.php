<?php

return [
    'indicator' => [
        'label' => 'RecruiterLabs AI',
        'open' => 'Open AI Activity',
    ],

    'state' => [
        'working' => 'Working',
        'waiting' => 'Waiting',
        'blocked' => 'Blocked',
        'up_to_date' => 'Up to date',
    ],

    'panel' => [
        'heading' => 'AI Activity',
        'description' => 'What RecruiterLabs AI is doing for this workspace right now.',
        'working_heading' => 'Working now',
        'waiting_heading' => 'Waiting',
        'blocked_heading' => 'Needs your attention',
        'recent_heading' => 'Recently completed',
        'empty' => 'Nothing is running. Everything the AI could do is up to date.',
        'recent_empty' => 'No AI work has been completed yet.',
    ],

    'role' => [
        'criteria_analyst' => 'Criteria analyst',
        'candidate_reviewer' => 'Candidate reviewer',
        'talent_matcher' => 'Talent matcher',
        'candidate_importer' => 'Candidate importer',
    ],

    'work' => [
        'criteria_preparing' => 'Preparing criteria for :job',
        'criteria_waiting_allowance' => 'Criteria for :job are on hold',
        'criteria_failed' => 'Criteria for :job could not be prepared',

        'evaluating_evaluating' => 'Evaluating :candidate for :job',
        'evaluating_waiting_criteria' => ':candidate is waiting to be evaluated for :job',
        'evaluating_waiting_allowance' => 'Evaluation of :candidate for :job is on hold',
        'evaluating_failed' => 'Evaluation of :candidate for :job could not be completed',
        'evaluating_many' => '{1} 1 candidate being evaluated for :job|[2,*] :count candidates being evaluated for :job',

        'sourcing_reviewing' => 'Reviewing the talent pool for :job',
        'sourcing_waiting_allowance' => 'Talent pool review for :job is on hold',
        'sourcing_failed' => 'Talent pool review for :job could not be completed',
        'sourcing_done' => 'Reviewed the talent pool for :job',

        'import_importing' => 'Importing candidates from :source',
        'import_paused' => 'Candidate import from :source is paused',
        'import_failed' => 'Candidate import from :source could not be completed',
        'import_done' => 'Imported candidates from :source',
    ],

    'done' => [
        'evaluated' => 'Evaluated :candidate for :job',
        'evaluated_many' => '{1} Evaluated 1 candidate for :job|[2,*] Evaluated :count candidates for :job',
        'criteria' => 'Prepared criteria for :job',
    ],

    'reason' => [
        'job_criteria' => 'Waiting for Job criteria',
        'ai_allowance' => 'Waiting for AI allowance',
        'criteria_failed' => 'Could not prepare the Job criteria',
        'evaluation_failed' => 'Could not complete candidate evaluation',
        'sourcing_failed' => 'Could not complete the talent pool review',
        'import_paused' => 'Waiting for you to resume the import',
        'import_failed' => 'Could not complete the candidate import',
    ],

    'fallback' => [
        'candidate' => 'a candidate',
        'job' => 'a job',
    ],

    'provenance' => [
        'generated' => 'AI-generated',
        'assisted' => 'AI-assisted',
        'tooltip' => 'This section was produced by RecruiterLabs AI. Hiring decisions remain yours.',
    ],
];
