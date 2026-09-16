<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetAlertNotification extends Notification
{
    use Queueable;

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Your password was reset — Obscura')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Your Obscura account password was reset using your recovery code.')
            ->line('**If this was you:** No further action is needed. Your private key has been re-sealed with your new password.')
            ->line('**If this was NOT you:**')
            ->line('• Your recovery code may be compromised — regenerate your keypair from Settings to create a new one')
            ->line('• Change your password immediately')
            ->line('• Contact support if you need assistance')
            ->line('**Security details:**')
            ->line('• The reset was performed at: ' . now()->toDateTimeString())
            ->line('• The recovery code used has been marked as consumed')
            ->line('• Your encrypted content is intact — only the password sealing changed')
            ->action('Go to Obscura', url('/'))
            ->line('— The Obscura team');
    }
}
