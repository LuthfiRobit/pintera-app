<?php
// app/Notifications/SesiReminderNotification.php

namespace App\Notifications;

use App\Mail\SesiReminderMail;
use App\Models\KasusSesi;
use App\Models\WhatsAppTemplate;
use Illuminate\Notifications\Notification;

class SesiReminderNotification extends Notification
{
    public function __construct(public KasusSesi $sesi)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail', 'whatsapp'];
    }

    public function toMail(object $notifiable): SesiReminderMail
    {
        $mail = new SesiReminderMail($this->sesi);

        if (method_exists($notifiable, 'routeNotificationForMail')) {
            $email = $notifiable->routeNotificationForMail();

            if ($email !== null && $email !== '') {
                $mail->to($email);
            }
        }

        return $mail;
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kasus_sesi_id' => $this->sesi->id,
            'message' => 'Pengingat: sesi pendampingan Anda besok, '.$this->sesi->dijadwalkan_pada->format('d M Y H:i').'.',
        ];
    }

    public function toWhatsApp(object $notifiable): ?string
    {
        return WhatsAppTemplate::renderKode('reminder_sesi_h1', [
            'nama_siswa' => $this->sesi->kasus?->siswa?->nama_lengkap ?? '',
            'tanggal_sesi' => $this->sesi->dijadwalkan_pada->format('d M Y H:i'),
            'lokasi_sesi' => $this->sesi->lokasi_mode,
        ]);
    }
}
