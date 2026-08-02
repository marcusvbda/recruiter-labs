<?php

namespace App\Enums;

enum Feature: string
{
    case Candidates = 'candidates';
    case OwnAiKey = 'own_ai_key';

    public function label(): string
    {
        return __('settings.features.'.$this->value);
    }
}
