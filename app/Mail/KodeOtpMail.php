<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class KodeOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $kodeOtp)
    {
    }

    public function build(): self
    {
        return $this->subject('Kode Verifikasi Pendaftaran SPMB')
            ->view('mail.kode-otp')
            ->with(['kodeOtp' => $this->kodeOtp]);
    }
}
