<?php

namespace App\Enums;

enum CandidateImportStatus: string
{
    case Draft = 'draft';
    case Validating = 'validating';
    case Ready = 'ready';
    case Processing = 'processing';
    case Paused = 'paused';
    case Completed = 'completed';
    case CompletedWithIssues = 'completed_with_issues';
    case Failed = 'failed';
    case Discarded = 'discarded';
    case Expired = 'expired';
}
