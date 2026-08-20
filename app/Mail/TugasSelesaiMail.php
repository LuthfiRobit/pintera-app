<?php
// app/Mail/TugasSelesaiMail.php

namespace App\Mail;

use App\Domains\Kasus\Models\KasusTugas;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TugasSelesaiMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public KasusTugas $tugas)
    {
    }

    public function build(): self
    {
        return $this->subject('Tugas Pendampingan Selesai')
            ->view('mail.tugas-selesai')
            ->with(['tugas' => $this->tugas]);
    }
}
