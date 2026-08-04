<?php

namespace App\Mail;

use App\Models\KasusSesi;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SesiDijadwalkanMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public KasusSesi $sesi)
    {
    }

    public function build(): self
    {
        return $this->subject('Sesi Pendampingan Dijadwalkan')
            ->view('mail.sesi-dijadwalkan')
            ->with(['sesi' => $this->sesi]);
    }
}
