<?php
// app/Notifications/KasusEskalasiNotification.php

namespace App\Notifications;

use App\Mail\KasusEskalasiMail;
use App\Models\Kasus;
use Illuminate\Notifications\Notification;

class KasusEskalasiNotification extends Notification
{
    public function __construct(public Kasus $kasus)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): KasusEskalasiMail
    {
        return new KasusEskalasiMail($this->kasus);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kasus_id' => $this->kasus->id,
            'message' => 'Kasus telah dieskalasi dan memerlukan perhatian Anda.',
        ];
    }
}
