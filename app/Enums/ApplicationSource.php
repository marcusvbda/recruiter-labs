<?php

namespace App\Enums;

enum ApplicationSource: string
{
    case Direct = 'direct';
    case CareerPage = 'career_page';
    case Referral = 'referral';
}
