<?php

namespace App\Services;

use App\Models\Pendaftaran;
use RuntimeException;

class KodePendaftaranGenerator
{
    private const MAKS_PERCOBAAN = 20;

    public function generate(int $lembagaId): string
    {
        $tahun = now()->year;
        $urutanAwal = Pendaftaran::where('lembaga_id', $lembagaId)
            ->whereYear('created_at', $tahun)
            ->count() + 1;

        for ($percobaan = 0; $percobaan < self::MAKS_PERCOBAAN; $percobaan++) {
            $kode = sprintf('REG-%d-%05d', $tahun, $urutanAwal + $percobaan);

            if (! Pendaftaran::where('lembaga_id', $lembagaId)->where('kode_pendaftaran', $kode)->exists()) {
                return $kode;
            }
        }

        throw new RuntimeException('Gagal membuat kode pendaftaran unik setelah '.self::MAKS_PERCOBAAN.' percobaan.');
    }
}
