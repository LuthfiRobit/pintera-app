<?php

namespace App\Notifications;

use App\Mail\TugasDitugaskanMail;
use App\Models\KasusTugas;
use Illuminate\Notifications\Notification;

class TugasDitugaskanNotification extends Notification
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

    public function toMail(object $notifiable): TugasDitugaskanMail
    {
        $mail = new TugasDitugaskanMail($this->tugas);

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
            'message' => 'Tugas baru "'.$this->tugas->judul.'" telah diberikan.',
        ];
    }
}
