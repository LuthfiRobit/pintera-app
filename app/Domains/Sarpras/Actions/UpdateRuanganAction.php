<?php

namespace App\Domains\Sarpras\Actions;

use App\Domains\Sarpras\DataTransferObjects\RuanganData;
use App\Domains\Sarpras\Models\Ruangan;
use Illuminate\Support\Facades\DB;

final class UpdateRuanganAction
{
    public function execute(Ruangan $ruangan, RuanganData $data): Ruangan
    {
        return DB::transaction(function () use ($ruangan, $data) {
            $ruangan->update([
                'gedung_id' => $data->gedungId,
                'kode_ruangan' => $data->kodeRuangan,
                'nama_ruangan' => $data->namaRuangan,
                'lantai' => $data->lantai,
                'jenis_ruangan' => $data->jenisRuangan,
                'kapasitas_siswa' => $data->kapasitasSiswa,
                'luas_m2' => $data->luasM2,
                'penanggung_jawab_guru_id' => $data->penanggungJawabGuruId,
                'is_shared' => $data->isShared,
                'is_aktif' => $data->isAktif,
            ]);

            return $ruangan->fresh();
        });
    }
}
