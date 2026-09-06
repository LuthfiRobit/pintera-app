<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\KartuSiswa;

use App\Domains\Akademik\Models\KartuSiswa;
use App\Models\Siswa;
use Illuminate\Support\Str;

final class GetOrCreateKartuQrSiswaAction
{
    public function execute(Siswa $siswa): KartuSiswa
    {
        $kartu = KartuSiswa::where('siswa_id', $siswa->id)->where('tipe', 'qr')->first();

        if ($kartu !== null) {
            return $kartu;
        }

        return KartuSiswa::create([
            'siswa_id' => $siswa->id,
            'tipe' => 'qr',
            'kode' => Str::random(32),
            'is_active' => true,
        ]);
    }
}
