<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Penilaian;

use App\Domains\Akademik\DataTransferObjects\UpdateKomponenPenilaianData;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Models\Semester;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateKomponenPenilaianAction
{
    /**
     * @throws ValidationException
     */
    public function execute(KomponenPenilaian $komponen, UpdateKomponenPenilaianData $data): KomponenPenilaian
    {
        return DB::transaction(function () use ($komponen, $data) {
            $dipakai = $komponen->asesmen()->exists() || $komponen->nilaiSiswa()->exists();

            // Subjek Penilaian dan Semester TIDAK BISA diubah sejak TP dibuat --
            // baik sudah dipakai maupun belum. Satu-satunya cara mengganti
            // Subjek/Semester adalah hapus lalu buat TP baru. Blok reassignment
            // lama SENGAJA dihapus (bukan dilewati) -- subjek_type/subjek_id/
            // semester_id di form manapun (Admin maupun Guru) memang tidak
            // pernah lagi dikirim ke sini.
            if (! $dipakai && $data->assessmentType !== null) {
                $komponen->assessment_type = $data->assessmentType;
            }

            Semester::where('id', $komponen->semester_id)->lockForUpdate()->first();

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
        });
    }
}
