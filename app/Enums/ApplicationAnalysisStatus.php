<?php

namespace App\Enums;

enum ApplicationAnalysisStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case PendingQuota = 'pending_quota';
}
