<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\MataPelajaran;

use App\Domains\Akademik\DataTransferObjects\MataPelajaranData;
use App\Domains\Akademik\Models\MataPelajaran;

final class UpdateMataPelajaranAction
{
    public function execute(MataPelajaran $mataPelajaran, MataPelajaranData $data): MataPelajaran
    {
        $mataPelajaran->update([
            'kode' => $data->kode,
            'nama' => $data->nama,
            'no_urut' => $data->noUrut,
            'tipe' => $data->tipe,
            'kelompok' => $data->kelompok,
            'status' => $data->status,
        ]);

        return $mataPelajaran;
    }
}
