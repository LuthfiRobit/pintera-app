<?php

namespace App\Notifications;

use App\Mail\ConsentDisetujuiMail;
use App\Models\Kasus;
use Illuminate\Notifications\Notification;

class ConsentDisetujuiNotification extends Notification
{
    public function __construct(public Kasus $kasus)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): ConsentDisetujuiMail
    {
        return new ConsentDisetujuiMail($this->kasus);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kasus_id' => $this->kasus->id,
            'message' => "Consent sesi pendampingan untuk {$this->kasus->siswa->nama_lengkap} telah disetujui.",
        ];
    }
}
