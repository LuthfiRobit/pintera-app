<?php
// app/Mail/SesiReminderMail.php

namespace App\Mail;

use App\Domains\Kasus\Models\KasusSesi;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SesiReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public KasusSesi $sesi)
    {
    }

    public function build(): self
    {
        return $this->subject('Pengingat: Sesi Pendampingan Besok')
            ->view('mail.sesi-reminder')
            ->with(['sesi' => $this->sesi]);
    }
}
