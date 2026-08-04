<?php

namespace App\Mail;

use App\Models\Kasus;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class KasusDiajukanMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Kasus $kasus)
    {
    }

    public function build(): self
    {
        return $this->subject('Kasus Pendampingan Baru Diajukan — '.$this->kasus->siswa->nama_lengkap)
            ->view('mail.kasus-diajukan')
            ->with(['kasus' => $this->kasus]);
    }
}
