<?php

namespace App\Enums;

enum CompanyRole: string
{
    case Owner = 'owner';
    case Member = 'member';

    public function label(): string
    {
        return __('company.roles.'.$this->value);
    }
}
