<?php

namespace App\Enums;

enum ApplicationCoverLetterType: string
{
    case None = 'none';
    case Text = 'text';
    case File = 'file';
}
