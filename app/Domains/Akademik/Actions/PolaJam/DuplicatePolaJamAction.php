<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\PolaJam;

use App\Domains\Akademik\Models\PolaJam;
use Illuminate\Support\Facades\DB;

final class DuplicatePolaJamAction
{
    public function execute(PolaJam $polaJam): array
    {
        return DB::transaction(function () use ($polaJam) {
            $newPola = PolaJam::create([
                'nama' => $polaJam->nama . ' (Salinan)',
                'lembaga_id' => $polaJam->lembaga_id,
            ]);

            foreach ($polaJam->jamPelajaran as $slot) {
                $newPola->jamPelajaran()->create([
                    'hari' => $slot->hari->value,
                    'urutan' => $slot->urutan,
                    'jam_mulai' => $slot->jam_mulai,
                    'jam_selesai' => $slot->jam_selesai,
                    'label' => $slot->label,
                    'is_pelajaran' => $slot->is_pelajaran,
                ]);
            }

            return [$newPola, $polaJam->jamPelajaran->count()];
        });
    }
}
