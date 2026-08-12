<?php
// app/Http/Middleware/ResolveActiveSiswa.php

namespace App\Http\Middleware;

use App\Models\Scopes\TenantScope;
use App\Models\Siswa;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveActiveSiswa
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $orangTua = $user->orangTua;

        abort_if($orangTua === null, 403, 'Akun ini tidak memiliki profil orang tua yang valid.');

        if ($request->has('switch_siswa')) {
            $value = $request->query('switch_siswa');

            if ($orangTua->siswa()->withoutGlobalScope(TenantScope::class)->whereKey($value)->exists()) {
                session(['active_siswa_id' => (int) $value]);
            }
            // Invalid/foreign id: silently ignored, matching ResolveTenant's
            // active_lembaga_id pattern — no error surfaced to the user.
        }

        $activeSiswaId = session('active_siswa_id');
        $activeSiswa = null;

        if ($activeSiswaId !== null) {
            $activeSiswa = $orangTua->siswa()->withoutGlobalScope(TenantScope::class)->whereKey($activeSiswaId)->first();
        }

        if ($activeSiswa === null) {
            $activeSiswa = $orangTua->siswa()->withoutGlobalScope(TenantScope::class)->wherePivot('is_kontak_utama', true)->first()
                ?? $orangTua->siswa()->withoutGlobalScope(TenantScope::class)->first();
        }

        $request->attributes->set('activeSiswa', $activeSiswa);

        return $next($request);
    }
}
