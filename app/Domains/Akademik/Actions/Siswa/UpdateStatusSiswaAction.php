<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Siswa;

use App\Enums\StatusSiswa;
use App\Models\Siswa;
use Illuminate\Support\Facades\DB;

final class UpdateStatusSiswaAction
{
    public function execute(Siswa $siswa, StatusSiswa $statusBaru): Siswa
    {
        return DB::transaction(function () use ($siswa, $statusBaru) {
            if ($siswa->status === $statusBaru) {
                return $siswa;
            }

            if ($statusBaru === StatusSiswa::Aktif) {
                $siswa->kelas_id = $siswa->kelas_terakhir_id;
                $siswa->kelas_terakhir_id = null;
            } elseif ($siswa->status === StatusSiswa::Aktif) {
                $siswa->kelas_terakhir_id = $siswa->kelas_id;
                $siswa->kelas_id = null;
            }

            $siswa->status = $statusBaru;
            $siswa->save();

            if ($siswa->user_id) {
                $siswa->user()->update(['is_active' => $statusBaru === StatusSiswa::Aktif]);
            }

            return $siswa;
        });
    }
}
