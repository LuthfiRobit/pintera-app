<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Kelas;

use App\Domains\Akademik\DataTransferObjects\KelasData;
use App\Domains\Akademik\Models\PolaJam;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\TahunAjaran;

final class UpdateKelasAction
{
    public function execute(Kelas $kelas, KelasData $data): Kelas
    {
        $tahunAjaran = TahunAjaran::find($data->tahunAjaranId);
        abort_if($tahunAjaran === null || $tahunAjaran->lembaga_id !== $kelas->lembaga_id, 404);

        $waliKelasGuruId = null;
        if ($data->waliKelasGuruId !== null) {
            $guru = Guru::find($data->waliKelasGuruId);
            abort_if($guru === null || $guru->lembaga_id !== $kelas->lembaga_id, 404);
            $waliKelasGuruId = $guru->id;
        }

        $polaJamId = null;
        if ($data->polaJamId !== null) {
            $polaJam = PolaJam::find($data->polaJamId);
            abort_if($polaJam === null || $polaJam->lembaga_id !== $kelas->lembaga_id, 404);
            $polaJamId = $polaJam->id;
        }

        $kelas->update([
            'tahun_ajaran_id' => $tahunAjaran->id,
            'nama' => $data->nama,
            'tingkat' => $data->tingkat,
            'fase_id' => $data->faseId,
            'wali_kelas_guru_id' => $waliKelasGuruId,
            'pola_jam_id' => $polaJamId,
        ]);

        return $kelas;
    }
}
