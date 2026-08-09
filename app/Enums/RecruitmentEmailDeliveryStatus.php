<?php

namespace App\Enums;

enum RecruitmentEmailDeliveryStatus: string
{
    case Pending = 'pending';
    case Sending = 'sending';
    case Delivered = 'delivered';
    case Ambiguous = 'ambiguous';
}
