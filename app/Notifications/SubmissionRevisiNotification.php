<?php
// app/Notifications/SubmissionRevisiNotification.php

namespace App\Notifications;

use App\Mail\SubmissionRevisiMail;
use App\Models\KasusTugasSubmission;
use Illuminate\Notifications\Notification;

class SubmissionRevisiNotification extends Notification
{
    public function __construct(public KasusTugasSubmission $submission)
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

    public function toMail(object $notifiable): SubmissionRevisiMail
    {
        $mail = new SubmissionRevisiMail($this->submission);

        $email = $notifiable->routeNotificationFor('mail');

        if ($email !== null && $email !== '') {
            $mail->to($email);
        }

        return $mail;
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kasus_tugas_submission_id' => $this->submission->id,
            'message' => 'Revisi diminta untuk tugas "'.$this->submission->tugas->judul.'".',
        ];
    }
}
