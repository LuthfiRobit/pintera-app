<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\KartuSiswa;

use App\Domains\Akademik\Models\KartuSiswa;
use App\Models\Siswa;
use Illuminate\Support\Str;

final class GenerateUlangKartuQrSiswaAction
{
    public function __construct(
        private readonly GetOrCreateKartuQrSiswaAction $getOrCreateKartuQrSiswaAction,
    ) {}

    public function execute(Siswa $siswa): KartuSiswa
    {
        $kartu = $this->getOrCreateKartuQrSiswaAction->execute($siswa);

        $kartu->update(['kode' => Str::random(32), 'is_active' => true]);

        return $kartu->fresh();
    }
}
