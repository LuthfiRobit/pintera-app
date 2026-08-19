<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Penilaian;

use App\Domains\Akademik\DataTransferObjects\KomponenPenilaianData;
use App\Domains\Akademik\Models\KomponenPenilaian;
use Illuminate\Validation\ValidationException;

final class CreateKomponenPenilaianAction
{
    /**
     * @throws ValidationException
     */
    public function execute(KomponenPenilaianData $data): KomponenPenilaian
    {
        $existingSum = KomponenPenilaian::where('mata_pelajaran_id', $data->mataPelajaranId)
            ->where('semester_id', $data->semesterId)
            ->sum('bobot');

        if (($existingSum + $data->bobot) > 100) {
            $remaining = max(0, 100 - $existingSum);
            throw ValidationException::withMessages([
                'bobot' => "Total bobot melebihi 100%. Sisa bobot yang tersedia untuk mata pelajaran ini adalah {$remaining}%.",
            ]);
        }

        return KomponenPenilaian::create([
            'mata_pelajaran_id' => $data->mataPelajaranId,
            'semester_id' => $data->semesterId,
            'kode' => $data->kode,
            'deskripsi' => $data->deskripsi,
            'bobot' => $data->bobot,
            'kktp' => $data->kktp,
            'kktp_minimal' => $data->kktpMinimal,
            'elemen_cp' => $data->elemenCp,
        ]);
    }
}
