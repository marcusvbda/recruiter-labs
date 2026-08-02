<?php

namespace App\Enums;

enum AiUsageStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
