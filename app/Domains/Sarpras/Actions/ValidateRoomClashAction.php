<?php

namespace App\Domains\Sarpras\Actions;

use App\Domains\Akademik\Models\JamPelajaran;
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
        $jamPelajaranBaru = JamPelajaran::findOrFail($jamPelajaranId);

        $query = JadwalPelajaran::query()
            ->where('ruangan_id', $ruanganId)
            ->where('semester_id', $semesterId)
            ->whereHas('jamPelajaran', function ($q) use ($jamPelajaranBaru) {
                $q->where('hari', $jamPelajaranBaru->hari)
                    ->where('jam_mulai', '<', $jamPelajaranBaru->jam_selesai)
                    ->where('jam_selesai', '>', $jamPelajaranBaru->jam_mulai);
            });

        if ($ignoreJadwalId) {
            $query->where('id', '!=', $ignoreJadwalId);
        }

        return $query->exists();
    }
}
