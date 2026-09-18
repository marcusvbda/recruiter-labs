<?php

namespace App\Enums;

enum CandidateCommunicationMessageStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Sending = 'sending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Ambiguous = 'ambiguous';
}
