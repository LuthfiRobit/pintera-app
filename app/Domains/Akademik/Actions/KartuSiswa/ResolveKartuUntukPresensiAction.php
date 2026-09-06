<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\KartuSiswa;

use App\Domains\Akademik\Exceptions\KartuKelasMismatchException;
use App\Domains\Akademik\Exceptions\KartuLembagaMismatchException;
use App\Domains\Akademik\Exceptions\KartuTidakValidException;
use App\Domains\Akademik\Models\KartuSiswa;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Models\Siswa;

final class ResolveKartuUntukPresensiAction
{
    public function execute(string $kode, SesiPembelajaran $sesi, int $lembagaId): Siswa
    {
        $siswa = KartuSiswa::resolveSiswa($kode);

        if ($siswa === null) {
            throw new KartuTidakValidException;
        }

        if ((int) $siswa->kelas_id !== (int) $sesi->kelas_id) {
            throw new KartuKelasMismatchException;
        }

        if ((int) $siswa->lembaga_id !== $lembagaId) {
            throw new KartuLembagaMismatchException;
        }

        return $siswa;
    }
}
