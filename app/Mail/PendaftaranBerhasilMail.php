<?php

namespace App\Mail;

use App\Models\Pendaftaran;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PendaftaranBerhasilMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Pendaftaran $pendaftaran)
    {
    }

    public function build(): self
    {
        return $this->subject('Pendaftaran SPMB Berhasil — '.$this->pendaftaran->kode_pendaftaran)
            ->view('mail.pendaftaran-berhasil')
            ->with(['pendaftaran' => $this->pendaftaran]);
    }
}
