<?php
// app/Notifications/KasusDikembalikanNotification.php

namespace App\Notifications;

use App\Mail\KasusDikembalikanMail;
use App\Models\Kasus;
use Illuminate\Notifications\Notification;

class KasusDikembalikanNotification extends Notification
{
    public function __construct(public Kasus $kasus)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): KasusDikembalikanMail
    {
        return new KasusDikembalikanMail($this->kasus);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kasus_id' => $this->kasus->id,
            'message' => 'Kasus telah dikembalikan kepada Anda untuk dilanjutkan.',
        ];
    }
}
