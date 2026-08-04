<?php

namespace App\Notifications;

use App\Mail\KonselorDipilihMail;
use App\Models\Kasus;
use Illuminate\Notifications\Notification;

class KonselorDipilihNotification extends Notification
{
    public function __construct(public Kasus $kasus)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): KonselorDipilihMail
    {
        return new KonselorDipilihMail($this->kasus);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kasus_id' => $this->kasus->id,
            'message' => 'Konselor telah dipilih. Persetujuan Anda diperlukan.',
        ];
    }
}
