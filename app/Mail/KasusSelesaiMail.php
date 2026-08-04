<?php
// app/Mail/KasusSelesaiMail.php

namespace App\Mail;

use App\Models\Kasus;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class KasusSelesaiMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Kasus $kasus)
    {
    }

    public function build(): self
    {
        return $this->subject('Kasus Pendampingan Selesai')
            ->view('mail.kasus-selesai')
            ->with(['kasus' => $this->kasus]);
    }
}
