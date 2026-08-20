<?php

namespace App\Domains\Kasus\Services;

use App\Domains\Kasus\DataTransferObjects\KonselorKandidat;
use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\Siswa;
use Illuminate\Support\Collection;

class KonselorAllocationResolver
{
    public function kandidatUntuk(Siswa $siswa): Collection
    {
        $guruBk = Guru::withoutGlobalScopes()
            ->where('jenis_ptk', 'guru_bk')
            ->where('lembaga_id', $siswa->lembaga_id)
            ->where('status_aktif', 'aktif')
            ->get();

        if ($guruBk->isNotEmpty()) {
            return $guruBk->map(fn (Guru $guru) => new KonselorKandidat('guru', $guru));
        }

        $karyawanPool = Karyawan::withoutGlobalScopes()
            ->whereNull('lembaga_id')
            ->where('yayasan_id', $siswa->lembaga->yayasan_id)
            ->where('status_aktif', 'aktif')
            ->whereHas('jenisKaryawan', fn ($q) => $q->where('is_konselor', true))
            ->get();

        return $karyawanPool->map(fn (Karyawan $karyawan) => new KonselorKandidat('karyawan', $karyawan));
    }
}
