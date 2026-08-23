<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Actions\JenisKaryawan;

use App\Domains\Sdm\DataTransferObjects\JenisKaryawanMasterData;
use App\Domains\Sdm\Models\JenisKaryawanMaster;

final class UpdateJenisKaryawanAction
{
    public function execute(JenisKaryawanMaster $jenisKaryawanMaster, JenisKaryawanMasterData $data): JenisKaryawanMaster
    {
        $jenisKaryawanMaster->update(['nama' => $data->nama]);

        return $jenisKaryawanMaster->fresh()->loadCount('karyawan');
    }
}
