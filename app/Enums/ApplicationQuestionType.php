<?php

namespace App\Enums;

enum ApplicationQuestionType: string
{
    case Text = 'text';
    case Number = 'number';
    case Textarea = 'textarea';

    public function label(): string
    {
        return __('jobs.application.question_types.'.$this->value);
    }
}
