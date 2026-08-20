<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\PolaJam;

use App\Domains\Akademik\Models\JamPelajaran;
use Illuminate\Validation\ValidationException;

final class UpdateJamPelajaranAction
{
    public function execute(JamPelajaran $jamPelajaran, string $hari, int $urutan, string $label, string $jamMulai, string $jamSelesai, bool $isPelajaran): JamPelajaran
    {
        if ($this->tabrakanSlot($jamPelajaran->pola_jam_id, $hari, $urutan, $jamPelajaran->id)) {
            throw ValidationException::withMessages([
                'urutan' => 'Urutan ini sudah dipakai pada hari yang sama di pola jam ini.',
            ]);
        }

        $jamPelajaran->update([
            'hari' => $hari,
            'urutan' => $urutan,
            'label' => $label,
            'jam_mulai' => $jamMulai,
            'jam_selesai' => $jamSelesai,
            'is_pelajaran' => $isPelajaran,
        ]);

        return $jamPelajaran->fresh();
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
