<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The "Reset your password" email (AUD-008). Laravel's built-in reset email,
 * reworded for DOC-MON. The link opens /reset-password/{token} and works once,
 * for 60 minutes (config/auth.php → passwords.users.expire).
 */
class ResetPasswordNotification extends ResetPassword
{
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Reset your DOC-MON password')
            ->greeting('Hi!')
            ->line('Someone asked to reset the password of your DOC-MON account. If it was you, click the button to choose a new one.')
            ->action('Choose a new password', $url)
            ->line('The link works once and expires in 60 minutes.')
            ->line('If you didn\'t ask for this, ignore this email: your password stays the same.');
    }
}
