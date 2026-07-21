<?php
// app/Http/Controllers/Portal/Auth/VerifikasiOtpController.php

namespace App\Http\Controllers\Portal\Auth;

use App\Models\AkunPendaftar;
use App\Models\Pendaftaran;
use App\Models\VerifikasiEmailOtp;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class VerifikasiOtpController extends BaseController
{
    private const RESEND_COOLDOWN_SECONDS = 60;

    public function create(): View
    {
        $email = session('portal_register_email_pending');

        $detikTersisa = 0;
        if ($email) {
            $otpTerakhir = VerifikasiEmailOtp::where('email', $email)->whereNull('verified_at')->latest('id')->first();

            if ($otpTerakhir) {
                $detikTersisa = max(0, self::RESEND_COOLDOWN_SECONDS - $otpTerakhir->created_at->diffInSeconds(now()));
            }
        }

        return view('portal.auth.verifikasi-otp', [
            'email' => $email,
            'detikTersisa' => $detikTersisa,
        ]);
    }

    public function store(Request $request, OtpService $otpService): RedirectResponse
    {
        $data = $request->validate([
            'kode_otp' => ['required', 'string'],
        ]);

        $email = session('portal_register_email_pending');

        if (! $email || ! $otpService->verifikasi($email, $data['kode_otp'])) {
            return back()->withErrors(['kode_otp' => 'Kode salah, kedaluwarsa, atau sudah dipakai.']);
        }

        $akun = AkunPendaftar::where('email', $email)->firstOrFail();
        $akun->forceFill(['email_verified_at' => now()])->save();

        Pendaftaran::where('email_pendaftaran', $email)
            ->whereNull('akun_pendaftar_id')
            ->update(['akun_pendaftar_id' => $akun->id]);

        Auth::guard('portal')->login($akun);
        session()->forget('portal_register_email_pending');

        return redirect()->route('portal.dashboard');
    }

    public function kirimUlang(OtpService $otpService): RedirectResponse
    {
        $email = session('portal_register_email_pending');

        if ($email) {
            $otpService->kirim($email);
        }

        return redirect()->route('portal.verifikasi-otp')->with('status', 'Kode verifikasi baru sudah dikirim.');
    }
}
