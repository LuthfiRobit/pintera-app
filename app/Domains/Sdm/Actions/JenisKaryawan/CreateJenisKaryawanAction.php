<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Actions\JenisKaryawan;

use App\Domains\Sdm\DataTransferObjects\JenisKaryawanMasterData;
use App\Domains\Sdm\Models\JenisKaryawanMaster;

final class CreateJenisKaryawanAction
{
    public function execute(JenisKaryawanMasterData $data): JenisKaryawanMaster
    {
        return JenisKaryawanMaster::create(['nama' => $data->nama])->loadCount('karyawan');
    }
}
