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
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): SubmissionRevisiMail
    {
        return new SubmissionRevisiMail($this->submission);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kasus_tugas_submission_id' => $this->submission->id,
            'message' => 'Revisi diminta untuk tugas "'.$this->submission->tugas->judul.'".',
        ];
    }
}
