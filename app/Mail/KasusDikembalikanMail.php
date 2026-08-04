<?php
// app/Mail/KasusDikembalikanMail.php

namespace App\Mail;

use App\Models\Kasus;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class KasusDikembalikanMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Kasus $kasus)
    {
    }

    public function build(): self
    {
        return $this->subject('Kasus Dikembalikan untuk Dilanjutkan')
            ->view('mail.kasus-dikembalikan')
            ->with(['kasus' => $this->kasus]);
    }
}
