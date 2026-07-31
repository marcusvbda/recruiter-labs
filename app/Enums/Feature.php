<?php

namespace App\Enums;

enum Feature: string
{
    case Candidates = 'candidates';

    public function label(): string
    {
        return __('settings.features.'.$this->value);
    }
}
