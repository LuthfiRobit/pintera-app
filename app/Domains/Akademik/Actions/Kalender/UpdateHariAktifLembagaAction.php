<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Kalender;

use App\Domains\Akademik\DataTransferObjects\HariAktifLembagaData;
use App\Models\Lembaga;

final class UpdateHariAktifLembagaAction
{
    public function execute(Lembaga $lembaga, HariAktifLembagaData $data): Lembaga
    {
        $hariLibur = array_values(array_diff(range(0, 6), $data->hariAktif));

        $lembaga->update(['hari_libur_mingguan' => $hariLibur]);

        return $lembaga->fresh();
    }
}
