<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Penilaian;

use App\Domains\Akademik\DataTransferObjects\UpdateKomponenPenilaianData;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Models\Semester;
use Illuminate\Validation\ValidationException;

final class UpdateKomponenPenilaianAction
{
    /**
     * @throws ValidationException
     */
    public function execute(KomponenPenilaian $komponen, UpdateKomponenPenilaianData $data): KomponenPenilaian
    {
        $dipakai = $komponen->asesmen()->exists() || $komponen->nilaiSiswa()->exists();

        if (! $dipakai && $data->subjekType !== null && $data->subjekId !== null && $data->semesterId !== null) {
            $komponen->subjek_type = $data->subjekType;
            $komponen->subjek_id = $data->subjekId;
            $komponen->semester_id = $data->semesterId;
            $komponen->lembaga_id = Semester::findOrFail($data->semesterId)->lembaga_id;
            if ($data->assessmentType !== null) {
                $komponen->assessment_type = $data->assessmentType;
            }
        }

        $newBobot = $data->bobot ?? $komponen->bobot;
        $existingSum = KomponenPenilaian::where('subjek_type', $komponen->subjek_type)
            ->where('subjek_id', $komponen->subjek_id)
            ->where('semester_id', $komponen->semester_id)
            ->where('id', '!=', $komponen->id)
            ->sum('bobot');

        if (($existingSum + $newBobot) > 100) {
            $remaining = max(0, 100 - $existingSum);
            throw ValidationException::withMessages([
                'bobot' => "Total bobot melebihi 100%. Sisa bobot yang tersedia untuk subjek ini adalah {$remaining}%.",
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
