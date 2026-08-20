<?php

namespace App\Notifications;

use App\Mail\SesiDijadwalkanMail;
use App\Domains\Kasus\Models\KasusSesi;
use Illuminate\Notifications\Notification;

class SesiDijadwalkanNotification extends Notification
{
    public function __construct(public KasusSesi $sesi)
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

    public function toMail(object $notifiable): SesiDijadwalkanMail
    {
        $mail = new SesiDijadwalkanMail($this->sesi);

        $email = $notifiable->routeNotificationFor('mail');

        if ($email !== null && $email !== '') {
            $mail->to($email);
        }

        return $mail;
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kasus_sesi_id' => $this->sesi->id,
            'message' => 'Sesi pendampingan telah dijadwalkan pada '.$this->sesi->dijadwalkan_pada->format('d M Y H:i').'.',
        ];
    }
}
