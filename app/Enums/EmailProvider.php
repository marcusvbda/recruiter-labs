<?php

namespace App\Enums;

enum EmailProvider: string
{
    case Resend = 'resend';
    case Gmail = 'gmail';
}
