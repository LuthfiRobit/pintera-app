<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\PolaJam;

use App\Domains\Akademik\DataTransferObjects\AssignKelasData;
use App\Domains\Akademik\Models\PolaJam;
use App\Models\Kelas;
use Illuminate\Validation\ValidationException;

final class AssignKelasToPolaJamAction
{
    public function execute(PolaJam $polaJam, AssignKelasData $data): void
    {
        $kelasTerpilih = Kelas::whereIn('id', $data->kelasIds)->get();

        if ($kelasTerpilih->count() !== count($data->kelasIds)) {
            throw ValidationException::withMessages([
                'kelas_ids' => 'Salah satu kelas yang dipilih tidak ditemukan.',
            ]);
        }

        foreach ($kelasTerpilih as $kelas) {
            if ($kelas->lembaga_id !== $polaJam->lembaga_id) {
                throw ValidationException::withMessages([
                    'kelas_ids' => 'Kelas dan pola jam harus berasal dari lembaga yang sama.',
                ]);
            }
        }

        Kelas::where('pola_jam_id', $polaJam->id)->whereNotIn('id', $data->kelasIds)->update(['pola_jam_id' => null]);
        Kelas::whereIn('id', $data->kelasIds)->update(['pola_jam_id' => $polaJam->id]);
    }
}
