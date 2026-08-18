<?php

namespace App\Enums;

enum InterviewCalendarSyncStatus: string
{
    case Pending = 'pending';
    case Synced = 'synced';
    case Failed = 'failed';
}
