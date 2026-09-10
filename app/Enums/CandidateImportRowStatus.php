<?php

namespace App\Enums;

enum CandidateImportRowStatus: string
{
    case Pending = 'pending';
    case Excluded = 'excluded';
    case Succeeded = 'succeeded';
    case NeedsReview = 'needs_review';
    case Failed = 'failed';
}
