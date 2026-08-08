<?php

namespace App\Enums;

enum AutomationEventType: string
{
    case ApplicationSubmitted = 'application_submitted';
    case StatusChanged = 'status_changed';

    public function label(): string
    {
        return __('event-hooks.event_types.'.$this->value);
    }
}
