<?php

namespace App\Enums;

/**
 * The recruiter-selected drafting task. These are intentionally explicit: a
 * follow-up is never inferred from a lack of inbound communication.
 */
enum CandidateCommunicationDraftPurpose: string
{
    case InitialOutreach = 'initial_outreach';
    case FollowUp = 'follow_up';
}
