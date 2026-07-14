<?php

namespace App\Services;

use App\Mail\KodeOtpMail;
use App\Models\VerifikasiEmailOtp;
use Illuminate\Support\Facades\Mail;

class OtpService
{
    public function kirim(string $email): void
    {
        VerifikasiEmailOtp::where('email', $email)->whereNull('verified_at')->delete();

        $kode = (string) random_int(100000, 999999);

        VerifikasiEmailOtp::create([
            'email' => $email,
            'kode_otp' => $kode,
            'expires_at' => now()->addMinutes(10),
        ]);

        Mail::to($email)->send(new KodeOtpMail($kode));
    }

    public function verifikasi(string $email, string $kode): bool
    {
        $otp = VerifikasiEmailOtp::where('email', $email)
            ->where('kode_otp', $kode)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $otp) {
            return false;
        }

        $otp->update(['verified_at' => now()]);

        return true;
    }
}
