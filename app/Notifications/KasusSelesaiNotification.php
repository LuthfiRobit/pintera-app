<?php
// app/Notifications/KasusSelesaiNotification.php

namespace App\Notifications;

use App\Mail\KasusSelesaiMail;
use App\Domains\Kasus\Models\Kasus;
use Illuminate\Notifications\Notification;

class KasusSelesaiNotification extends Notification
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

    public function toMail(object $notifiable): KasusSelesaiMail
    {
        $mail = new KasusSelesaiMail($this->kasus);

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
            'message' => 'Kasus pendampingan telah selesai.',
        ];
    }
}
