<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\KartuSiswa;

use App\Domains\Akademik\Models\KartuSiswa;
use App\Models\Siswa;

final class NonaktifkanKartuQrSiswaAction
{
    public function execute(Siswa $siswa): void
    {
        KartuSiswa::where('siswa_id', $siswa->id)->where('tipe', 'qr')->update(['is_active' => false]);
    }
}
