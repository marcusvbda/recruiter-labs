<?php

namespace App\Enums;

enum Feature: string
{
    case Leads = 'leads';

    public function label(): string
    {
        return __('settings.features.'.$this->value);
    }
}
