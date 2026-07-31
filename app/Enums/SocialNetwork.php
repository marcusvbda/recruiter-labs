<?php

namespace App\Enums;

enum SocialNetwork: string
{
    case Instagram = 'instagram';
    case LinkedIn = 'linkedin';
    case X = 'x';
    case Facebook = 'facebook';
    case TikTok = 'tiktok';
    case WhatsApp = 'whatsapp';
    case Other = 'other';

    public function label(): string
    {
        return __('candidates.networks.'.$this->value);
    }
}
