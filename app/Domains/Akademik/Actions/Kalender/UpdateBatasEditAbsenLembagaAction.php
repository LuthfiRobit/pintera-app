<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Kalender;

use App\Domains\Akademik\DataTransferObjects\BatasEditAbsenLembagaData;
use App\Models\Lembaga;

final class UpdateBatasEditAbsenLembagaAction
{
    public function execute(Lembaga $lembaga, BatasEditAbsenLembagaData $data): Lembaga
    {
        $lembaga->update(['batas_edit_absen_hari' => $data->batasHari]);

        return $lembaga->fresh();
    }
}
