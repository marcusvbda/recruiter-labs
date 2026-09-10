<?php

namespace App\Enums;

enum CandidateMaterialPreparationStatus: string
{
    case Stored = 'stored';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case NoReadableText = 'no_readable_text';
    case Failed = 'failed';
    case FileUnavailable = 'file_unavailable';
}
