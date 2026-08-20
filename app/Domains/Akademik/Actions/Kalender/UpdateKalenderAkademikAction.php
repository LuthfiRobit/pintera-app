<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Kalender;

use App\Domains\Akademik\DataTransferObjects\KalenderAkademikData;
use App\Domains\Akademik\Models\KalenderAkademik;

final class UpdateKalenderAkademikAction
{
    public function execute(KalenderAkademik $kalenderAkademik, KalenderAkademikData $data): KalenderAkademik
    {
        $kalenderAkademik->update([
            'nama' => $data->nama,
            'tipe' => $data->tipe,
            'keterangan' => $data->keterangan,
        ]);

        return $kalenderAkademik->fresh();
    }
}
