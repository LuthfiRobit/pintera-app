<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\PolaJam;

use App\Domains\Akademik\DataTransferObjects\JamPelajaranData;
use App\Domains\Akademik\Models\JamPelajaran;
use App\Enums\Hari;

final class CreateJamPelajaranAction
{
    /**
     * @return array{berhasil: array<int, string>, dilewati: array<int, string>}
     */
    public function execute(JamPelajaranData $data): array
    {
        $berhasil = [];
        $dilewati = [];

        foreach ($data->hari as $hari) {
            if ($this->tabrakanSlot($data->polaJamId, $hari, $data->urutan)) {
                $dilewati[] = $hari;
                continue;
            }

            JamPelajaran::create([
                'pola_jam_id' => $data->polaJamId,
                'hari' => $hari,
                'urutan' => $data->urutan,
                'label' => $data->label,
                'jam_mulai' => $data->jamMulai,
                'jam_selesai' => $data->jamSelesai,
                'is_pelajaran' => $data->isPelajaran,
            ]);
            $berhasil[] = $hari;
        }

        return ['berhasil' => $berhasil, 'dilewati' => $dilewati];
    }

    private function tabrakanSlot(int $polaJamId, string $hari, int $urutan, ?int $kecualiId = null): bool
    {
        return JamPelajaran::where('pola_jam_id', $polaJamId)
            ->where('hari', $hari)
            ->where('urutan', $urutan)
            ->when($kecualiId, fn ($q) => $q->where('id', '!=', $kecualiId))
            ->exists();
    }
}
