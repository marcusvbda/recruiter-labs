<?php

namespace App\Notifications;

use Filament\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailNotification extends VerifyEmail
{
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject(__('auth.verify_email.subject'))
            ->line(__('auth.verify_email.line_1'))
            ->action(__('auth.verify_email.action'), $url)
            ->line(__('auth.verify_email.line_2'));
    }
}
