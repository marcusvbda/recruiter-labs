<?php

namespace App\Enums;

enum AutomationActionType: string
{
    case SendEmail = 'send_email';

    public function label(): string
    {
        return __('event-hooks.action_types.'.$this->value);
    }
}
