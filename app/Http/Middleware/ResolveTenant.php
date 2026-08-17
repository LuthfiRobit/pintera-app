<?php

namespace App\Http\Middleware;

use App\Models\Lembaga;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        // Explicitly scoped to the 'web' guard: this middleware is appended globally
        // to the 'web' middleware group (bootstrap/app.php), which routes/portal.php
        // also inherits. Tenant/lembaga switching is an admin (User model) concept
        // only — auth()->user() would resolve whatever guard is currently the
        // "default" guard, which normally stays 'web' but can be flipped by test
        // helpers like actingAs($akun, 'portal'). Scoping here avoids calling
        // User-only methods (e.g. widestScopeLevel()) on a portal AkunPendaftar.
        $user = auth()->guard('web')->user();

        if ($user && $user->widestScopeLevel() === 'yayasan' && $request->has('switch_lembaga')) {
            $value = $request->query('switch_lembaga');

            if ($value === 'all') {
                session()->forget('active_lembaga_id');
            } elseif ($user->yayasan_id && Lembaga::whereKey($value)->where('yayasan_id', $user->yayasan_id)->exists()) {
                session(['active_lembaga_id' => (int) $value]);
            }
        }

        return $next($request);
    }
}
