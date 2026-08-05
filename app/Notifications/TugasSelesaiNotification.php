<?php
// app/Notifications/TugasSelesaiNotification.php

namespace App\Notifications;

use App\Mail\TugasSelesaiMail;
use App\Models\KasusTugas;
use Illuminate\Notifications\Notification;

class TugasSelesaiNotification extends Notification
{
    public function __construct(public KasusTugas $tugas)
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

    public function toMail(object $notifiable): TugasSelesaiMail
    {
        $mail = new TugasSelesaiMail($this->tugas);

        $email = $notifiable->routeNotificationFor('mail');

        if ($email !== null && $email !== '') {
            $mail->to($email);
        }

        return $mail;
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kasus_tugas_id' => $this->tugas->id,
            'message' => 'Tugas "'.$this->tugas->judul.'" telah selesai.',
        ];
    }
}
