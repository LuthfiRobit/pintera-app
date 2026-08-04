<?php

namespace App\Mail;

use App\Models\KasusTugas;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TugasDitugaskanMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public KasusTugas $tugas)
    {
    }

    public function build(): self
    {
        return $this->subject('Tugas Pendampingan Baru')
            ->view('mail.tugas-ditugaskan')
            ->with(['tugas' => $this->tugas]);
    }
}
