<?php

namespace App\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvitationNotification extends Notification
{
    public function __construct(
        private readonly string $token,
        private readonly CarbonInterface $expiresAt,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = config('app.frontend_url').'/accept-invitation?'.http_build_query(['token' => $this->token]);

        return (new MailMessage)
            ->subject('You have been invited to '.config('app.name'))
            ->greeting('Hello '.$notifiable->name.',')
            ->line('An account has been created for you. Set your password to get started.')
            ->action('Accept invitation', $url)
            ->line('This link expires on '.$this->expiresAt->timezone('Africa/Accra')->format('j F Y, H:i').' (Ghana time) and can be used once.')
            ->line('If you were not expecting this, you can ignore this email.');
    }
}
