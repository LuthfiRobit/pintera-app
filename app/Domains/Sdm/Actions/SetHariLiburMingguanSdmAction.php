<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Actions;

use App\Domains\Sdm\DataTransferObjects\HariKerjaSdmData;
use App\Models\Lembaga;

final class SetHariLiburMingguanSdmAction
{
    public function execute(Lembaga $lembaga, HariKerjaSdmData $data): Lembaga
    {
        $hariLibur = array_values(array_diff(range(0, 6), $data->hariKerja));

        $lembaga->update(['hari_libur_mingguan_sdm' => $hariLibur]);

        return $lembaga->fresh();
    }
}
