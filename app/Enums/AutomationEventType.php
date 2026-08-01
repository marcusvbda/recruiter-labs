<?php

namespace App\Enums;

enum AutomationEventType: string
{
    case ApplicationSubmitted = 'application_submitted';
    case StatusChanged = 'status_changed';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return __('automation-events.event_types.'.$this->value);
    }
}
