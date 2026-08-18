<?php

namespace App\Enums;

enum InterviewRsvpStatus: string
{
    case NeedsAction = 'needs_action';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Tentative = 'tentative';
}
