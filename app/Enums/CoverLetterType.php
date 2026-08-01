<?php

namespace App\Enums;

enum CoverLetterType: string
{
    case Text = 'text';
    case File = 'file';

    public function label(): string
    {
        return __('jobs.application.cover_letter_types.'.$this->value);
    }
}
