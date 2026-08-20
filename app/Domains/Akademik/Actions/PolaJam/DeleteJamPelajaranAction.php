<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\PolaJam;

use App\Domains\Akademik\Models\JamPelajaran;
use Illuminate\Validation\ValidationException;

final class DeleteJamPelajaranAction
{
    public function execute(JamPelajaran $jamPelajaran): void
    {
        if ($jamPelajaran->jadwalPelajaran()->exists()) {
            throw ValidationException::withMessages([
                'jam_pelajaran' => 'Slot ini masih dipakai di Jadwal Pelajaran — hapus jadwalnya dulu sebelum menghapus slot ini.',
            ]);
        }

        $jamPelajaran->delete();
    }
}
