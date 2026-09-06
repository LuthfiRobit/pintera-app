<?php

namespace App\Notifications\Akademik;

use App\Domains\Akademik\Models\Presensi;
use Illuminate\Notifications\Notification;

class PresensiPengecualianNotification extends Notification
{
    public function __construct(public Presensi $presensi) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return ['presensi_id' => $this->presensi->id];
    }
}
