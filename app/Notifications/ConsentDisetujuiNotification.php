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
        $channels = ['database'];

        if (filled($notifiable->routeNotificationFor('mail'))) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): ConsentDisetujuiMail
    {
        $mail = new ConsentDisetujuiMail($this->kasus);

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
            'message' => "Consent sesi pendampingan untuk {$this->kasus->siswa->nama_lengkap} telah disetujui.",
        ];
    }
}
