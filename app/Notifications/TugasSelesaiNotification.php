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
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): TugasSelesaiMail
    {
        return new TugasSelesaiMail($this->tugas);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kasus_tugas_id' => $this->tugas->id,
            'message' => 'Tugas "'.$this->tugas->judul.'" telah selesai.',
        ];
    }
}
