<?php

namespace App\Enums;

enum PlanChangeSource: string
{
    case ManualSettings = 'manual_settings';
    case Checkout = 'checkout';
    case Webhook = 'webhook';
    case Renewal = 'renewal';
    case Cancellation = 'cancellation';
    case Admin = 'admin';
}
