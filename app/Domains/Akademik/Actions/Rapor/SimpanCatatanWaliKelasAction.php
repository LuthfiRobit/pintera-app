<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Rapor;

use App\Domains\Akademik\DataTransferObjects\CatatanWaliKelasData;
use App\Domains\Akademik\Models\CatatanWaliKelas;

final class SimpanCatatanWaliKelasAction
{
    public function execute(CatatanWaliKelasData $data): CatatanWaliKelas
    {
        return CatatanWaliKelas::updateOrCreate(
            ['siswa_id' => $data->siswaId, 'semester_id' => $data->semesterId],
            [
                'catatan_sikap' => $data->catatanSikap,
                'catatan_perkembangan' => $data->catatanPerkembangan,
                'tinggi_badan_cm' => $data->tinggiBadanCm,
                'berat_badan_kg' => $data->beratBadanKg,
                'lingkar_kepala_cm' => $data->lingkarKepalaCm,
                'ekstrakurikuler' => $data->ekstrakurikuler,
                'prestasi' => $data->prestasi,
                'pkl_info' => $data->pklInfo,
                'keterangan_kenaikan' => $data->keteranganKenaikan,
            ]
        );
    }
}
