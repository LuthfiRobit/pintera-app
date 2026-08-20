<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\PolaJam;

use App\Domains\Akademik\Models\PolaJam;
use Illuminate\Validation\ValidationException;

final class DeletePolaJamAction
{
    public function execute(PolaJam $polaJam): void
    {
        if ($polaJam->kelas()->exists()) {
            throw ValidationException::withMessages([
                'pola_jam' => 'Pola jam ini masih dipakai oleh satu atau lebih kelas — lepaskan dulu sebelum menghapus.',
            ]);
        }

        if ($polaJam->jamPelajaran()->whereHas('jadwalPelajaran')->exists()) {
            throw ValidationException::withMessages([
                'pola_jam' => 'Pola jam ini memiliki jam pelajaran yang sudah dipakai di Jadwal Pelajaran — hapus jadwalnya dulu sebelum menghapus pola jam ini.',
            ]);
        }

        $polaJam->delete();
    }
}
