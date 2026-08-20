<?php

declare(strict_types=1);

namespace App\Domains\Kasus\Actions\Pengajuan;

use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Enums\StatusKasus;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use Illuminate\Support\Collection;

final class ListKasusUntukUserAction
{
    public function execute(User $user): Collection
    {
        if ($user->hasRole('siswa')) {
            $kasusList = Kasus::with('siswa')->where('siswa_id', $user->siswa?->id)->latest()->get();
        } elseif ($user->hasRole('orang_tua')) {
            // Orang tua accounts have no lembaga_id of their own, so the default TenantScope
            // on Kasus (a real, non-null lembaga_id) would fail-closed to zero rows for them.
            // Bypass it here. Show every kasus this orang_tua either submitted themselves OR
            // is the kontak utama for (matching the exact access rule show() uses) — filtering
            // only by diajukan_oleh_orang_tua_id missed every kasus a GURU submitted for their
            // child, at any status, since that column stays null in that case.
            $orangTuaId = $user->orangTua?->id;
            $kasusList = Kasus::withoutGlobalScope(TenantScope::class)
                ->with(['siswa' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)])
                ->where(fn ($q) => $q
                    ->where('diajukan_oleh_orang_tua_id', $orangTuaId)
                    ->orWhereHas('siswa', fn ($q2) => $q2->withoutGlobalScope(TenantScope::class)
                        ->whereHas('orangTua', fn ($q3) => $q3->where('orang_tua.id', $orangTuaId)
                            ->where('siswa_orang_tua.is_kontak_utama', true))))
                ->latest()->get();
        } elseif ($user->hasRole('karyawan_pool') || $user->hasRole('karyawan_lembaga')) {
            $karyawanId = $user->karyawan()->withoutGlobalScope(TenantScope::class)->first()?->id;
            $kasusList = Kasus::withoutGlobalScope(TenantScope::class)
                ->with(['siswa' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)])
                ->where('konselor_karyawan_id', $karyawanId)->latest()->get();
        } elseif ($user->hasRole('guru')) {
            $kasusList = Kasus::with('siswa')
                ->where(fn ($q) => $q->where('diajukan_oleh_guru_id', $user->guru?->id)
                    ->orWhere('konselor_guru_id', $user->guru?->id))
                ->latest()->get();
        } else {
            $kasusList = Kasus::with('siswa')->latest()->get();
        }

        return $kasusList->sortByDesc(fn ($k) => $k->status === StatusKasus::Eskalasi ? 1 : 0)->values();
    }
}
