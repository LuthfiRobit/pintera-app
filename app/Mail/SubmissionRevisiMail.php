<?php
// app/Mail/SubmissionRevisiMail.php

namespace App\Mail;

use App\Domains\Kasus\Models\KasusTugasSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SubmissionRevisiMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public KasusTugasSubmission $submission)
    {
    }

    public function build(): self
    {
        return $this->subject('Revisi Diminta untuk Tugas Pendampingan')
            ->view('mail.submission-revisi')
            ->with(['submission' => $this->submission]);
    }
}
