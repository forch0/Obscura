<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification
{
    use Queueable;

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Welcome to Obscura')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Your Obscura account has been created successfully.')
            ->line('Obscura is a private, end-to-end encrypted gallery. Your images and content names are encrypted in your browser before they ever reach the server — even we cannot see them.')
            ->line('**Next steps:**')
            ->line('• Complete your keypair generation (if you haven\'t already)')
            ->line('• Save your recovery code in a secure location')
            ->line('• Verify your email address')
            ->action('Go to Obscura', url('/'))
            ->line('If you did not create this account, please ignore this email.')
            ->line('— The Obscura team');
    }
}
