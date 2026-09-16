<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RecoveryCodeGeneratedNotification extends Notification
{
    use Queueable;

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Recovery code generated — Obscura')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('A recovery code has been generated for your Obscura account.')
            ->line('**For your security, the recovery code is NOT included in this email.**')
            ->line('You should have seen it displayed once during keypair generation. If you did not save it:')
            ->line('• You can regenerate your keypair from Settings (this will create a new recovery code)')
            ->line('• Without the recovery code, forgetting your password means losing access to your encrypted data')
            ->line('**What the recovery code does:**')
            ->line('• Allows you to recover your private key if you forget your password')
            ->line('• Is the only way to regain access to your encrypted content after password loss')
            ->line('• Should be stored in a secure, offline location (printed or written down)')
            ->action('Go to Obscura', url('/'))
            ->line('— The Obscura team');
    }
}
