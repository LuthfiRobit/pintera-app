<?php

namespace App\Notifications;

use App\Mail\KasusDiajukanMail;
use App\Models\Kasus;
use Illuminate\Notifications\Notification;

class KasusDiajukanNotification extends Notification
{
    public function __construct(public Kasus $kasus)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): KasusDiajukanMail
    {
        return new KasusDiajukanMail($this->kasus);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kasus_id' => $this->kasus->id,
            'message' => "Kasus baru diajukan untuk siswa {$this->kasus->siswa->nama_lengkap}.",
        ];
    }
}
