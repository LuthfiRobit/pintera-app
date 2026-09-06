<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Services;

use App\Domains\Akademik\Models\PiketHarian;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Models\Guru;

final class PiketAccessChecker
{
    public function bisaAkses(SesiPembelajaran $sesi, Guru $guru): bool
    {
        if ($sesi->guru_id === $guru->id) {
            return true;
        }

        return PiketHarian::where('lembaga_id', $sesi->lembaga_id)
            ->where('guru_id', $guru->id)
            ->where('tanggal', $sesi->tanggal->toDateString())
            ->exists();
    }
}
