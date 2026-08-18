<?php

namespace App\Enums;

enum InterviewStatus: string
{
    case Pending = 'pending';
    case Scheduled = 'scheduled';
    case Cancelled = 'cancelled';
}
