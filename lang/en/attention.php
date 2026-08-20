<?php

return [
    'heading' => 'Needs your attention',
    'description' => 'Derived from where your hiring processes stand right now.',
    'empty_heading' => 'No recruitment items currently need your attention.',
    'empty_description' => 'Interviews, evaluations and hiring targets are all in a healthy state.',
    'hidden' => '{1} 1 more item is not listed here.|[2,*] :count more items are not listed here.',
    'job_heading' => 'Needs attention in this process',
    'severities' => [
        'critical' => 'Broken',
        'warning' => 'Waiting on you',
        'info' => 'Worth knowing',
    ],
    'days' => '{0} less than a day|{1} 1 day|[2,*] :count days',
    'items' => [
        'interview_declined' => [
            'title' => ':candidate declined the interview',
            'explanation' => 'The invitation for :date was declined in the calendar.',
            'action' => 'Reschedule interview',
        ],
        'interview_calendar_failed' => [
            'title' => 'Interview with :candidate is not in the calendar',
            'explanation' => 'The interview on :date exists here, but its calendar event could not be created, so the candidate may hold no invitation.',
            'action' => 'Open interviews',
        ],
        'calendar_reconnect_required' => [
            'title' => 'Your calendar connection expired',
            'explanation' => '{1} 1 interview you own can no longer be kept in sync until the calendar is reconnected.|[2,*] :count interviews you own can no longer be kept in sync until the calendar is reconnected.',
            'action' => 'Reconnect calendar',
        ],
        'evaluation_failed' => [
            'title' => 'Evaluation failed for :candidate',
            'explanation' => 'The candidate evaluation ended in an error, so there is no fit or evidence to read. The application itself is untouched.',
            'action' => 'Open evaluation',
        ],
        'evaluation_blocked_by_quota' => [
            'title' => 'Evaluations are waiting for AI allowance',
            'explanation' => '{1} 1 application is queued and cannot be evaluated until the workspace has allowance again.|[2,*] :count applications are queued and cannot be evaluated until the workspace has allowance again.',
            'action' => 'Review AI usage',
        ],
        'stage_overdue' => [
            'title' => ':candidate is waiting in :stage',
            'explanation' => 'Waiting :waited in :stage — this stage is configured for attention after :threshold.',
            'action' => 'Open application',
        ],
        'decision_pending' => [
            'title' => ':candidate is waiting for a decision',
            'explanation' => 'Reached :stage :waited ago and has no interview scheduled next.',
            'action' => 'Open application',
        ],
        'job_stalled' => [
            'title' => ':job has applications but no progress',
            'explanation' => '{1} 1 candidate applied and none has reached an interview, a final stage or a hire.|[2,*] :count candidates applied and none has reached an interview, a final stage or a hire.',
            'action' => 'Open pipeline',
        ],
        'job_ending_without_finalists' => [
            'title' => ':job ends soon with nobody close to a hire',
            'explanation' => 'The campaign ends on :date and there are no finalists and no hires.',
            'action' => 'Review job',
        ],
        'hiring_target_reached' => [
            'title' => ':job reached its hiring target',
            'explanation' => ':hired of :target positions filled. Decide whether to pause applications, unpublish the job, or keep recruiting.',
            'action' => 'Review job',
        ],
        'hiring_target_near' => [
            'title' => ':job is one hire from its target',
            'explanation' => ':hired of :target positions filled.',
            'action' => 'Open pipeline',
        ],
    ],
];
