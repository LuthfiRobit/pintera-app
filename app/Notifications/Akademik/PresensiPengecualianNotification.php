<?php

namespace App\Notifications\Akademik;

use App\Domains\Akademik\Models\Presensi;
use App\Models\WhatsAppTemplate;
use Illuminate\Notifications\Notification;

class PresensiPengecualianNotification extends Notification
{
    public function __construct(public Presensi $presensi) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (filled($notifiable->routeNotificationFor('mail'))) {
            $channels[] = 'mail';
        }

        $channels[] = 'whatsapp';

        return $channels;
    }

    public function toDatabase(?object $notifiable): array
    {
        return [
            'presensi_id' => $this->presensi->id,
            'message' => "Presensi {$this->presensi->siswa->nama_lengkap} tercatat {$this->presensi->status->label()} pada {$this->presensi->sesiPembelajaran->tanggal->translatedFormat('d F Y')}.",
        ];
    }

    public function toWhatsApp(?object $notifiable): ?string
    {
        return WhatsAppTemplate::renderKode('presensi_pengecualian', [
            'nama_siswa' => $this->presensi->siswa->nama_lengkap,
            'status' => $this->presensi->status->label(),
            'tanggal' => $this->presensi->sesiPembelajaran->tanggal->translatedFormat('d F Y'),
            'keterangan' => $this->presensi->keterangan ?: '-',
        ]);
    }
}
