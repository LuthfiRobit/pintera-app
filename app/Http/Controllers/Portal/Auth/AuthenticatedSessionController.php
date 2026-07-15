<?php
// app/Http/Controllers/Portal/Auth/AuthenticatedSessionController.php

namespace App\Http\Controllers\Portal\Auth;

use App\Models\AkunPendaftar;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends BaseController
{
    public function create(): View
    {
        return view('portal.auth.login');
    }

    public function store(Request $request, OtpService $otpService): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $akun = AkunPendaftar::where('email', $credentials['email'])->first();

        if (! $akun || ! \Illuminate\Support\Facades\Hash::check($credentials['password'], $akun->password)) {
            throw ValidationException::withMessages([
                'email' => 'Email atau password salah.',
            ]);
        }

        if (! $akun->email_verified_at) {
            $otpService->kirim($akun->email);
            session(['portal_register_email_pending' => $akun->email]);

            return redirect()->route('portal.verifikasi-otp')
                ->withErrors(['email' => 'Email Anda belum diverifikasi. Kode baru sudah dikirim.']);
        }

        Auth::guard('portal')->login($akun, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('portal.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('portal')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }
}
