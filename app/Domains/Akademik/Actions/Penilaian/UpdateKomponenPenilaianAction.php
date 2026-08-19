<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Penilaian;

use App\Domains\Akademik\DataTransferObjects\UpdateKomponenPenilaianData;
use App\Domains\Akademik\Models\KomponenPenilaian;
use Illuminate\Validation\ValidationException;

final class UpdateKomponenPenilaianAction
{
    /**
     * @throws ValidationException
     */
    public function execute(KomponenPenilaian $komponen, UpdateKomponenPenilaianData $data): KomponenPenilaian
    {
        $dipakai = $komponen->asesmen()->exists() || $komponen->nilaiSiswa()->exists();

        if (! $dipakai && $data->mataPelajaranId !== null && $data->semesterId !== null) {
            $komponen->mata_pelajaran_id = $data->mataPelajaranId;
            $komponen->semester_id = $data->semesterId;
        }

        $newBobot = $data->bobot ?? $komponen->bobot;
        $existingSum = KomponenPenilaian::where('mata_pelajaran_id', $komponen->mata_pelajaran_id)
            ->where('semester_id', $komponen->semester_id)
            ->where('id', '!=', $komponen->id)
            ->sum('bobot');

        if (($existingSum + $newBobot) > 100) {
            $remaining = max(0, 100 - $existingSum);
            throw ValidationException::withMessages([
                'bobot' => "Total bobot melebihi 100%. Sisa bobot yang tersedia untuk mata pelajaran ini adalah {$remaining}%.",
            ]);
        }

        $komponen->kode = $data->kode;
        $komponen->deskripsi = $data->deskripsi;
        $komponen->bobot = $newBobot;
        $komponen->kktp = $data->kktp;
        $komponen->kktp_minimal = $data->kktpMinimal;
        $komponen->save();

        return $komponen;
    }
}
