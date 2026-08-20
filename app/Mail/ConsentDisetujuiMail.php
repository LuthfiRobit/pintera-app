<?php

namespace App\Mail;

use App\Domains\Kasus\Models\Kasus;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ConsentDisetujuiMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Kasus $kasus)
    {
    }

    public function build(): self
    {
        return $this->subject('Persetujuan Diterima — Kasus Resmi Ditugaskan')
            ->view('mail.consent-disetujui')
            ->with(['kasus' => $this->kasus]);
    }
}
