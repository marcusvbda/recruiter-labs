<?php

namespace App\Enums;

enum Limit: string
{
    case Users = 'users';
    case Jobs = 'jobs';
    case Applications = 'applications';
    case AiAnalyses = 'ai_analyses';

    public function label(): string
    {
        return __('settings.limits.'.$this->value);
    }
}
