<?php

namespace App\Notifications;

use App\Mail\SesiDijadwalkanMail;
use App\Models\KasusSesi;
use Illuminate\Notifications\Notification;

class SesiDijadwalkanNotification extends Notification
{
    public function __construct(public KasusSesi $sesi)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): SesiDijadwalkanMail
    {
        return new SesiDijadwalkanMail($this->sesi);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kasus_sesi_id' => $this->sesi->id,
            'message' => 'Sesi pendampingan telah dijadwalkan pada '.$this->sesi->dijadwalkan_pada->format('d M Y H:i').'.',
        ];
    }
}
