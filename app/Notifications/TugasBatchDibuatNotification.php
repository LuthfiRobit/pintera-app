<?php
// app/Notifications/TugasBatchDibuatNotification.php

namespace App\Notifications;

use App\Mail\TugasBatchDibuatMail;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class TugasBatchDibuatNotification extends Notification
{
    /**
     * @param  Collection<int, \App\Models\KasusTugas>  $barisTugas
     */
    public function __construct(public Collection $barisTugas)
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

    public function toMail(object $notifiable): TugasBatchDibuatMail
    {
        $mail = new TugasBatchDibuatMail($this->barisTugas);

        $email = $notifiable->routeNotificationFor('mail');

        if ($email !== null && $email !== '') {
            $mail->to($email);
        }

        return $mail;
    }

    public function toDatabase(object $notifiable): array
    {
        $pertama = $this->barisTugas->first();

        return [
            'kasus_tugas_batch_id' => $pertama->batch_id,
            'jumlah_baris' => $this->barisTugas->count(),
            'message' => 'Tugas baru "'.$pertama->judul.'" telah diberikan ('
                .$this->barisTugas->count().' baris, '.ucfirst($pertama->frekuensi).').',
        ];
    }
}
