<?php

namespace App\Mail;

use App\Domains\Kasus\Models\Kasus;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class KonselorDipilihMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Kasus $kasus)
    {
    }

    public function build(): self
    {
        return $this->subject('Konselor Dipilih — Persetujuan Diperlukan')
            ->view('mail.konselor-dipilih')
            ->with(['kasus' => $this->kasus]);
    }
}
