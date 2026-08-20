<?php

return [
    'navigation_label' => 'Overview',
    'title' => 'Overview',
    'subtitle' => 'What is happening in recruiting right now.',
    'summary' => [
        'active_jobs' => '{0} active processes|{1} active process|[2,*] active processes',
        'active_applications' => '{0} active applications|{1} active application|[2,*] active applications',
        'finalists' => '{0} finalists|{1} finalist|[2,*] finalists',
        'hired' => '{0} hires|{1} hire|[2,*] hires',
    ],
    'agenda' => [
        'heading' => 'Your next interviews',
        'today' => 'Today',
        'tomorrow' => 'Tomorrow',
        'empty_heading' => 'Nothing scheduled.',
        'empty_description' => 'Interviews you own will appear here.',
        'hidden' => '{1} 1 more ahead|[2,*] :count more ahead',
    ],
    'processes' => [
        'heading' => 'Hiring processes',
        'view_all' => 'View all',
        'empty_heading' => 'No active hiring processes.',
        'empty_description' => 'Publish a job to start receiving candidates.',
        'hidden' => '{1} 1 more active process is not listed here.|[2,*] :count more active processes are not listed here.',
    ],
];
