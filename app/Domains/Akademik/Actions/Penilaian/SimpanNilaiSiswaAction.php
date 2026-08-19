<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Penilaian;

use App\Domains\Akademik\DataTransferObjects\NilaiSiswaBatchData;
use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\NilaiSiswa;
use Illuminate\Support\Facades\DB;

final class SimpanNilaiSiswaAction
{
    public function execute(Asesmen $asesmen, NilaiSiswaBatchData $data): void
    {
        $komponenIds = $asesmen->komponenPenilaian()->pluck('komponen_penilaian.id');
        $siswaIds = $asesmen->kelas->siswa()->pluck('id');

        DB::transaction(function () use ($asesmen, $data, $komponenIds, $siswaIds) {
            foreach ($data->nilai as $siswaId => $perKomponen) {
                if (! $siswaIds->contains((int) $siswaId)) {
                    continue;
                }

                foreach ($perKomponen as $komponenId => $values) {
                    if (! $komponenIds->contains((int) $komponenId)) {
                        continue;
                    }

                    NilaiSiswa::updateOrCreate(
                        ['asesmen_id' => $asesmen->id, 'siswa_id' => $siswaId, 'komponen_penilaian_id' => $komponenId],
                        [
                            'nilai_angka' => isset($values['nilai_angka']) && $values['nilai_angka'] !== '' ? (int) $values['nilai_angka'] : null,
                            'catatan' => $values['catatan'] ?? null,
                        ]
                    );
                }
            }
        });
    }
}
