<?php
// app/Mail/KasusEskalasiMail.php

namespace App\Mail;

use App\Models\Kasus;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class KasusEskalasiMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Kasus $kasus)
    {
    }

    public function build(): self
    {
        return $this->subject('Kasus Dieskalasi — Perlu Perhatian Admin')
            ->view('mail.kasus-eskalasi')
            ->with(['kasus' => $this->kasus]);
    }
}
