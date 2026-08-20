<?php

namespace App\Notifications;

use App\Mail\KasusDiajukanMail;
use App\Domains\Kasus\Models\Kasus;
use Illuminate\Notifications\Notification;

class KasusDiajukanNotification extends Notification
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

    public function toMail(object $notifiable): KasusDiajukanMail
    {
        $mail = new KasusDiajukanMail($this->kasus);

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
            'message' => "Kasus baru diajukan untuk siswa {$this->kasus->siswa->nama_lengkap}.",
        ];
    }
}
