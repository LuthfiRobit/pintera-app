<?php
// app/Http/Controllers/Portal/Auth/RegisteredAkunController.php

namespace App\Http\Controllers\Portal\Auth;

use App\Models\AkunPendaftar;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class RegisteredAkunController extends BaseController
{
    public function create(): View
    {
        return view('portal.auth.register');
    }

    public function store(\Illuminate\Http\Request $request, OtpService $otpService): RedirectResponse
    {
        $data = Validator::make($request->all(), [
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:akun_pendaftar,email'],
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::min(8)->letters()->mixedCase()->numbers()],
        ])->validate();

        AkunPendaftar::create([
            'nama' => $data['nama'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $otpService->kirim($data['email']);

        session(['portal_register_email_pending' => $data['email']]);

        return redirect()->route('portal.verifikasi-otp');
    }
}
