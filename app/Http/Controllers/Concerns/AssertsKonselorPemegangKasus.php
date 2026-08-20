<?php
// app/Http/Controllers/Concerns/AssertsKonselorPemegangKasus.php

namespace App\Http\Controllers\Concerns;

use App\Domains\Kasus\Models\Kasus;
use App\Models\Scopes\TenantScope;

trait AssertsKonselorPemegangKasus
{
    private function assertKonselorPemegangKasus(Kasus $kasus): void
    {
        $user = auth()->user();
        $karyawanId = $user->karyawan()->withoutGlobalScope(TenantScope::class)->first()?->id;
        $isKonselor = ($kasus->konselor_guru_id !== null && $kasus->konselor_guru_id === $user->guru?->id)
            || ($kasus->konselor_karyawan_id !== null && $kasus->konselor_karyawan_id === $karyawanId);

        abort_unless($isKonselor, 403);
    }
}
