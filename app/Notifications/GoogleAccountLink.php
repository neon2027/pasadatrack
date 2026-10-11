<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class GoogleAccountLink extends Notification
{
    public function __construct(public int $challengeId, public string $token) {}

    /** @return array<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Connect your Google account to PasadaTrack')
            ->line('Confirm that you own this email address before connecting your Google account.')
            ->line('Open this link in the same browser where you started sign-in. The link expires in 15 minutes. If you did not request this, ignore the email.')
            ->action('Confirm Google account', URL::temporarySignedRoute('google.link.show', now()->addMinutes(15), ['challenge' => $this->challengeId, 'token' => $this->token]));
    }
}
