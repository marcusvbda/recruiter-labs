<?php

namespace App\Enums;

enum ApplicationSource: string
{
    case Direct = 'direct';
    case Referral = 'referral';
}
