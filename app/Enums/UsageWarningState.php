<?php

namespace App\Enums;

enum UsageWarningState: string
{
    case Normal = 'normal';
    case Attention = 'attention';
    case Critical = 'critical';
    case Reached = 'reached';
}
