<?php

return [
    'errors' => [
        'dismissed_must_be_restored_first' => 'This candidate was dismissed for this job. Restore them first to consider them again.',
        'not_dismissed' => 'Only a dismissed candidate can be restored.',
        'candidate_already_in_job' => 'This candidate is already part of this job\'s hiring process.',
    ],

    'not_confirmed' => [
        'title' => 'Confirm evaluation criteria to start sourcing',
        'description' => 'Sourcing searches this workspace\'s existing candidates against the job\'s current confirmed hiring criteria. Confirm the criteria first, then request matches.',
        'action' => 'Review evaluation criteria',
    ],

    'status' => [
        'not_started' => 'Not searched yet',
        'searching' => 'Searching',
        'completed' => 'Completed',
        'failed' => 'Search failed',
        'blocked' => 'Blocked — AI allowance unavailable',
        'outdated' => 'Outdated — criteria changed',
        'predates_pool' => 'New candidates or CVs added — refresh to include them',
    ],

    'panel' => [
        'status_label' => 'Sourcing status',
        'find_matches' => 'Find matches',
        'outdated_hint' => 'These results used a previous version of the criteria. Run a new search to refresh them.',
        'search_started' => 'Sourcing search started. This can take a while for a large talent pool.',
        'saved' => 'Candidate saved for this job.',
        'dismissed' => 'Candidate dismissed for this job.',
        'restored' => 'Candidate restored to the suggestion list.',
        'candidate_removed' => 'Removed candidate',
        'no_talent_pool' => 'No internal talent pool exists yet. Sourcing will have candidates to consider once people apply to your jobs or are added to the workspace.',
        'no_matches_yet' => 'Run a search to see potential matches from your existing candidates.',
        'no_current_matches' => 'No current potential matches. Run a new search once new candidates or updated information are available.',
        'summary' => ':considered candidates reviewed · :matched potential matches · :insufficient candidates had insufficient information for meaningful matching',
        'insufficient_information' => 'Insufficiently known — not enough submitted material to assess against these criteria.',
        'dismissed_heading' => 'Dismissed',
        'state_saved' => 'Saved',
        'already_in_job' => 'Already in this job\'s process',
        'restore_action' => 'Restore',
        'save_action' => 'Save',
        'dismiss_action' => 'Dismiss',
        'add_to_job_action' => 'Add to job',
        'open_candidate_action' => 'Open candidate',
    ],

    'match' => [
        'potential_match_label' => 'Potential Match',
        'evidence_coverage_label' => 'Evidence Coverage',
        'confidence_label' => 'Confidence',
        'confidence' => [
            'high' => 'High',
            'medium' => 'Medium',
            'low' => 'Low',
        ],
        'not_assessed' => 'Not enough information',
    ],

    'criteria' => [
        'strong_support_heading' => 'Strong support',
        'needs_validation_heading' => 'Needs validation',
        'insufficient_heading' => 'Insufficient information',
        'weight_label' => 'weight :weight/10',
        'evidence_submitted_on' => 'submitted :date',
    ],

    'history' => [
        'heading' => 'Previous interaction',
        'not_proof_of_fit' => 'Historical context only — it does not affect this Potential Match.',
        'applied_on' => 'applied :date',
        'reached_final_stage' => 'reached a final stage',
        'was_hired' => 'hired',
        'closed' => 'process closed',
        'more' => '+:count more',
    ],
];
