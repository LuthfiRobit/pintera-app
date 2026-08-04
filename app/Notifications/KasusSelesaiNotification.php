<?php
// app/Notifications/KasusSelesaiNotification.php

namespace App\Notifications;

use App\Mail\KasusSelesaiMail;
use App\Models\Kasus;
use Illuminate\Notifications\Notification;

class KasusSelesaiNotification extends Notification
{
    public function __construct(public Kasus $kasus)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): KasusSelesaiMail
    {
        return new KasusSelesaiMail($this->kasus);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kasus_id' => $this->kasus->id,
            'message' => 'Kasus pendampingan telah selesai.',
        ];
    }
}
