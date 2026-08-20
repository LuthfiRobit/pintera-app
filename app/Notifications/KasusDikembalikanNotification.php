<?php
// app/Notifications/KasusDikembalikanNotification.php

namespace App\Notifications;

use App\Mail\KasusDikembalikanMail;
use App\Domains\Kasus\Models\Kasus;
use Illuminate\Notifications\Notification;

class KasusDikembalikanNotification extends Notification
{
    public function __construct(public Kasus $kasus)
    {
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (filled($notifiable->routeNotificationFor('mail'))) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): KasusDikembalikanMail
    {
        $mail = new KasusDikembalikanMail($this->kasus);

        $email = $notifiable->routeNotificationFor('mail');

        if ($email !== null && $email !== '') {
            $mail->to($email);
        }

        return $mail;
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kasus_id' => $this->kasus->id,
            'message' => 'Kasus telah dikembalikan kepada Anda untuk dilanjutkan.',
        ];
    }
}
