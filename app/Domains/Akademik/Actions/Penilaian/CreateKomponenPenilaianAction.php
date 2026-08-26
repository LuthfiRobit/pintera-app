<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Penilaian;

use App\Domains\Akademik\DataTransferObjects\KomponenPenilaianData;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Models\Semester;
use Illuminate\Validation\ValidationException;

final class CreateKomponenPenilaianAction
{
    /**
     * @throws ValidationException
     */
    public function execute(KomponenPenilaianData $data): KomponenPenilaian
    {
        $existingSum = KomponenPenilaian::where('subjek_type', $data->subjekType)
            ->where('subjek_id', $data->subjekId)
            ->where('semester_id', $data->semesterId)
            ->sum('bobot');

        if (($existingSum + $data->bobot) > 100) {
            $remaining = max(0, 100 - $existingSum);
            throw ValidationException::withMessages([
                'bobot' => "Total bobot melebihi 100%. Sisa bobot yang tersedia untuk subjek ini adalah {$remaining}%.",
            ]);
        }

        return KomponenPenilaian::create([
            'subjek_type' => $data->subjekType,
            'subjek_id' => $data->subjekId,
            'semester_id' => $data->semesterId,
            // lembaga_id eksplisit dari Semester -- WAJIB utk subjek_type
            // elemen_cp karena ElemenCp sendiri tidak punya lembaga_id
            // (booted() hook di model hanya menangani jalur mata_pelajaran).
            'lembaga_id' => Semester::findOrFail($data->semesterId)->lembaga_id,
            'kode' => $data->kode,
            'deskripsi' => $data->deskripsi,
            'bobot' => $data->bobot,
            'kktp' => $data->kktp,
            'kktp_minimal' => $data->kktpMinimal,
        ]);
    }
}
