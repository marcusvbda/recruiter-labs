<?php

return [
    'heading' => 'Needs your attention',
    'empty_heading' => 'Everything is on track.',
    'empty_description' => 'No recruitment items currently need your attention.',
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
        'candidate_communication_failed' => [
            'title' => 'Communication to :candidate was not delivered',
            'explanation' => 'An authorised message could not be delivered. Review the communication before deciding whether to retry.',
            'action' => 'Open communication',
        ],
        'email_provider_needs_attention' => [
            'title' => 'Email provider needs attention',
            'explanation' => 'An authorised candidate communication could not proceed because its email provider is unavailable or needs reauthorisation.',
            'action' => 'Open email provider settings',
        ],
        'evaluation_blocked_by_quota' => [
            'title' => 'Evaluations are waiting for AI allowance',
            'explanation' => '{1} 1 application is queued and cannot be evaluated until the workspace has allowance again.|[2,*] :count applications are queued and cannot be evaluated until the workspace has allowance again.',
            'action' => 'Review AI usage',
        ],
        'criteria_ready_for_review' => [
            'title' => 'Criteria are ready to review for :job',
            'explanation' => 'AI prepared these criteria. Review and confirm them before they govern candidate evaluation or sourcing.',
            'action' => 'Review criteria',
        ],
        'criteria_preparation_failed' => [
            'title' => 'Criteria could not be prepared for :job',
            'explanation' => 'The criteria preparation did not complete, so there are no new criteria ready for review.',
            'action' => 'Review criteria',
        ],
        'criteria_blocked_by_quota' => [
            'title' => 'Criteria preparation is waiting for AI allowance for :job',
            'explanation' => 'Criteria preparation did not run because the workspace AI allowance was reached.',
            'action' => 'Review AI usage',
        ],
        'sourcing_ready' => [
            'title' => 'Internal sourcing is ready for :job',
            'explanation' => 'Current confirmed criteria and eligible candidates are available. Starting a sweep remains your decision.',
            'action' => 'Find matches',
        ],
        'sourcing_refresh_ready' => [
            'title' => 'Internal sourcing can be refreshed for :job',
            'criteria_explanation' => 'The previous sweep used an older criteria revision. Authorise a new sweep when ready.',
            'pool_explanation' => 'The previous sweep predates the current talent pool or candidate material. Authorise a new sweep when ready.',
            'action' => 'Refresh matches',
        ],
        'sourcing_blocked_by_quota' => [
            'title' => 'Internal sourcing is waiting for AI allowance for :job',
            'explanation' => 'The previous sweep stopped because the workspace AI allowance was reached.',
            'action' => 'Review AI usage',
        ],
        'sourcing_failed' => [
            'title' => 'Internal sourcing failed for :job',
            'explanation' => 'The previous sweep did not complete, so its result cannot be treated as a complete current search.',
            'action' => 'Review sourcing',
        ],
        'sourcing_results_ready_for_review' => [
            'title' => '{1} 1 potential match is ready for review|[2,*] :count potential matches are ready for review',
            'explanation' => 'These suggested matches still need an explicit recruiter decision.',
            'action' => 'Review matches',
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
            'explanation' => '{1} 1 of :applications candidates has been waiting past what their stage allows, and nobody has reached an interview, a final stage or a hire.|[2,*] :count of :applications candidates have been waiting past what their stage allows, and nobody has reached an interview, a final stage or a hire.',
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
