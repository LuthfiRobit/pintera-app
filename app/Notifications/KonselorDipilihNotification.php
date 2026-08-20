<?php

namespace App\Notifications;

use App\Mail\KonselorDipilihMail;
use App\Domains\Kasus\Models\Kasus;
use App\Models\WhatsAppTemplate;
use Illuminate\Notifications\Notification;

class KonselorDipilihNotification extends Notification
{
    public function __construct(public Kasus $kasus)
    {
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (filled($notifiable->routeNotificationFor('mail'))) {
            $channels[] = 'mail';
        }

        $channels[] = 'whatsapp';

        return $channels;
    }

    public function toMail(object $notifiable): KonselorDipilihMail
    {
        $mail = new KonselorDipilihMail($this->kasus);

        $email = $notifiable->routeNotificationFor('mail');

        if ($email !== null && $email !== '') {
            $mail->to($email);
        }

        return $mail;
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kasus_id' => $this->kasus->id,
            'message' => 'Konselor telah dipilih. Persetujuan Anda diperlukan.',
        ];
    }

    public function toWhatsApp(object $notifiable): ?string
    {
        $konselorNama = $this->kasus->konselorGuru?->nama ?? $this->kasus->konselorKaryawan?->nama ?? '';

        return WhatsAppTemplate::renderKode('consent_diminta', [
            'nama_siswa' => $this->kasus->siswa?->nama_lengkap ?? '',
            'nama_konselor' => $konselorNama,
        ]);
    }
}
