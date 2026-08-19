<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Penilaian;

use App\Domains\Akademik\Models\KomponenPenilaian;
use Illuminate\Validation\ValidationException;

final class DeleteKomponenPenilaianAction
{
    /**
     * @throws ValidationException
     */
    public function execute(KomponenPenilaian $komponen): void
    {
        if ($komponen->asesmen()->exists() || $komponen->nilaiSiswa()->exists()) {
            throw ValidationException::withMessages([
                'komponen_penilaian' => 'Komponen ini sudah dipakai pada asesmen atau nilai siswa — tidak bisa dihapus.',
            ]);
        }

        $komponen->delete();
    }
}
