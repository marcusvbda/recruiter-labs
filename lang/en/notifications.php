<?php

return [
    'candidate_import' => [
        'completed' => [
            'title' => 'Candidate import finished',
            'body' => ':source: :imported candidate(s) added to your pool, :unresolved row(s) still need a decision.',
            'action' => 'Open import',
        ],
        'failed' => [
            'title' => 'Candidate import could not finish',
            'body' => ':source stopped before every row was processed. Whatever imported is already in your pool.',
            'action' => 'Open import',
        ],
    ],

    'sourcing' => [
        'completed' => [
            'title' => 'Talent pool review finished for :job',
            'body' => ':reviewed profile(s) were reviewed against this role.',
            'action' => 'Open sourcing',
        ],
        'failed' => [
            'title' => 'Talent pool review stopped for :job',
            'body' => 'The review did not cover your whole pool. You can run it again when you are ready.',
            'action' => 'Open sourcing',
        ],
        'blocked' => [
            'title' => 'Talent pool review paused for :job',
            'body' => 'Your AI allowance ran out mid-review, so the rest of the pool was not seen.',
            'action' => 'Open sourcing',
        ],
    ],

    'communication' => [
        'failed' => [
            'title' => 'Email to :candidate was not delivered',
            'body' => 'The message could not be sent. Check the conversation and send it again.',
            'action' => 'Open conversation',
        ],
    ],

    'interview' => [
        'declined' => [
            'title' => ':candidate declined the interview',
            'body' => 'The invitation was declined in the calendar. The interview slot is still held here.',
            'action' => 'Open interviews',
        ],
    ],

    'criteria' => [
        'failed' => [
            'title' => 'Hiring criteria could not be prepared for :job',
            'body' => 'Candidates for this role are not being evaluated until the criteria are ready.',
            'action' => 'Open job',
        ],
    ],

    'ai_allowance' => [
        'blocked' => [
            'title' => 'AI allowance used up',
            'body' => 'Automatic work is on hold until more allowance is available.',
            'action' => 'Open AI settings',
        ],
    ],
];
