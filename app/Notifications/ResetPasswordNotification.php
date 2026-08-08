<?php

namespace App\Notifications;

use Filament\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject(__('auth.reset_password.subject'))
            ->line(__('auth.reset_password.line_1'))
            ->action(__('auth.reset_password.action'), $url)
            ->line(__('auth.reset_password.expires', [
                'count' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
            ]))
            ->line(__('auth.reset_password.line_2'));
    }
}
