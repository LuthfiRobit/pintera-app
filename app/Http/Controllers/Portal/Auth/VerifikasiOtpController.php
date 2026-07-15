<?php
// app/Http/Controllers/Portal/Auth/VerifikasiOtpController.php

namespace App\Http\Controllers\Portal\Auth;

use App\Models\AkunPendaftar;
use App\Models\Pendaftaran;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class VerifikasiOtpController extends BaseController
{
    public function create(): View
    {
        return view('portal.auth.verifikasi-otp');
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
}
