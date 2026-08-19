<?php

return [
    'navigation_label' => 'Overview',
    'title' => 'Overview',
    'subtitle' => 'What is happening in recruiting right now.',
    'stats' => [
        'open_jobs' => 'Open jobs',
        'draft_jobs' => '{0} No drafts|{1} 1 draft|[2,*] :count drafts',
        'active_applications' => 'Active applications',
        'interviewing' => '{0} Nobody interviewing|{1} 1 interviewing|[2,*] :count interviewing',
        'finalists' => 'Finalists',
        'hired' => '{0} No hires yet|{1} 1 hired|[2,*] :count hired',
        'upcoming_interviews' => 'Upcoming interviews',
        'upcoming_interviews_description' => 'Scheduled and not yet finished',
    ],
    'upcoming_interviews' => [
        'heading' => 'Next interviews',
        'description' => 'Your soonest commitments with candidates.',
        'empty' => 'No interviews scheduled.',
        'when' => 'When',
        'rsvp' => 'Candidate RSVP',
    ],
    'active_jobs' => [
        'heading' => 'Open processes',
        'description' => 'How far each open job has actually moved.',
        'empty' => 'No open jobs.',
    ],
];
