<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureAkunPendaftarVerified
{
    public function handle(Request $request, Closure $next)
    {
        $akun = Auth::guard('portal')->user();

        if (! $akun || ! $akun->email_verified_at) {
            return redirect()->route('portal.verifikasi-otp');
        }

        return $next($request);
    }
}
