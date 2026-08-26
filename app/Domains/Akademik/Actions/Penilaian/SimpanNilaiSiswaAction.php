<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Penilaian;

use App\Domains\Akademik\DataTransferObjects\NilaiSiswaBatchData;
use App\Domains\Akademik\Enums\StatusPengajuanRapor;
use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Domains\Akademik\Models\PengajuanRapor;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SimpanNilaiSiswaAction
{
    /**
     * @throws ValidationException
     */
    public function execute(Asesmen $asesmen, NilaiSiswaBatchData $data): void
    {
        $terkunci = PengajuanRapor::where('kelas_id', $asesmen->kelas_id)
            ->where('semester_id', $asesmen->semester_id)
            ->where('status', StatusPengajuanRapor::Disetujui)
            ->exists();

        if ($terkunci) {
            throw ValidationException::withMessages([
                'nilai' => 'Nilai untuk kelas dan semester ini sudah dikunci karena rapor sudah disetujui.',
            ]);
        }

        $tipePerKomponen = $asesmen->komponenPenilaian()->pluck('assessment_type', 'komponen_penilaian.id');
        $siswaIds = $asesmen->kelas->siswa()->pluck('id');

        DB::transaction(function () use ($asesmen, $data, $tipePerKomponen, $siswaIds) {
            foreach ($data->nilai as $siswaId => $perKomponen) {
                if (! $siswaIds->contains((int) $siswaId)) {
                    continue;
                }

                foreach ($perKomponen as $komponenId => $values) {
                    $tipe = $tipePerKomponen->get((int) $komponenId)?->value;
                    if ($tipe === null) {
                        continue;
                    }

                    $payload = match ($tipe) {
                        'numeric' => [
                            'nilai_angka' => isset($values['nilai_angka']) && $values['nilai_angka'] !== '' ? (int) $values['nilai_angka'] : null,
                            'predikat' => null,
                            'catatan' => $values['catatan'] ?? null,
                        ],
                        'narrative' => [
                            'nilai_angka' => null,
                            'predikat' => null,
                            'catatan' => $values['catatan'] ?? null,
                        ],
                        'predicate' => [
                            'nilai_angka' => null,
                            'predikat' => $values['predikat'] ?? null,
                            'catatan' => $values['catatan'] ?? null,
                        ],
                        default => null,
                    };

                    if ($payload === null) {
                        continue;
                    }

                    NilaiSiswa::updateOrCreate(
                        ['asesmen_id' => $asesmen->id, 'siswa_id' => $siswaId, 'komponen_penilaian_id' => $komponenId],
                        $payload
                    );
                }
            }
        });
    }
}
