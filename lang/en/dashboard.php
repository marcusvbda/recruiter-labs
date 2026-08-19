<?php

return [
    'navigation_label' => 'Overview',
    'title' => 'Overview',
    'subtitle' => 'What is happening in recruiting right now.',
    'stats' => [
        'active_jobs' => 'Active processes',
        'draft_jobs' => '{0} No drafts|{1} 1 draft|[2,*] :count drafts',
        'active_applications' => 'Active applications',
        'interviewing' => '{0} Nobody interviewing|{1} 1 interviewing|[2,*] :count interviewing',
        'finalists' => 'Finalists',
        'hired' => '{0} No hires yet|{1} 1 hired|[2,*] :count hired',
        'upcoming_interviews' => 'Your upcoming interviews',
        'upcoming_interviews_description' => 'Yours, scheduled and not yet finished',
    ],
    'upcoming_interviews' => [
        'heading' => 'Your next interviews',
        'description' => 'Your soonest commitments with candidates.',
        'empty' => 'You have no interviews scheduled.',
        'when' => 'When',
        'rsvp' => 'Candidate RSVP',
    ],
    'active_jobs' => [
        'heading' => 'Active processes',
        'description' => 'How far each active hiring process has actually moved.',
        'empty' => 'No active hiring processes.',
    ],
];
