<?php
// app/Notifications/SesiReminderNotification.php

namespace App\Notifications;

use App\Mail\SesiReminderMail;
use App\Models\KasusSesi;
use Illuminate\Notifications\Notification;

class SesiReminderNotification extends Notification
{
    public function __construct(public KasusSesi $sesi)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): SesiReminderMail
    {
        return new SesiReminderMail($this->sesi);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kasus_sesi_id' => $this->sesi->id,
            'message' => 'Pengingat: sesi pendampingan Anda besok, '.$this->sesi->dijadwalkan_pada->format('d M Y H:i').'.',
        ];
    }
}
