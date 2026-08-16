<?php

namespace App\Domains\Sarpras\Actions;

use App\Models\JadwalPelajaran;

final class ValidateRoomClashAction
{
    /**
     * Memeriksa apakah ruangan yang dipilih sudah digunakan pada semester & jam pelajaran yang sama.
     * Mengembalikan true jika ada bentrok (clash), false jika aman/bebas.
     */
    public function execute(
        int $ruanganId,
        int $semesterId,
        int $jamPelajaranId,
        ?int $ignoreJadwalId = null
    ): bool {
        $query = JadwalPelajaran::query()
            ->where('ruangan_id', $ruanganId)
            ->where('semester_id', $semesterId)
            ->where('jam_pelajaran_id', $jamPelajaranId);

        if ($ignoreJadwalId) {
            $query->where('id', '!=', $ignoreJadwalId);
        }

        return $query->exists();
    }
}
