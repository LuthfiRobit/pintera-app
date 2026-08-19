<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Services;

use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Models\MataPelajaran;
use App\Models\Semester;
use App\Models\Siswa;

final class CapaianKompetensiGenerator
{
    private const DEFAULT_AMBANG_KKTP = 75;

    /**
     * @return array{tertinggi: ?string, terendah: ?string}
     */
    public function generateNarasi(Siswa $siswa, MataPelajaran $mapel, Semester $semester): array
    {
        $komponenList = KomponenPenilaian::where('mata_pelajaran_id', $mapel->id)
            ->where('semester_id', $semester->id)
            ->get();

        if ($komponenList->isEmpty()) {
            return ['tertinggi' => null, 'terendah' => null];
        }

        $asesmenIds = Asesmen::where('mata_pelajaran_id', $mapel->id)
            ->where('semester_id', $semester->id)
            ->pluck('id');

        $skorPerKomponen = [];
        foreach ($komponenList as $komponen) {
            $rataRata = NilaiSiswa::where('siswa_id', $siswa->id)
                ->where('komponen_penilaian_id', $komponen->id)
                ->whereIn('asesmen_id', $asesmenIds)
                ->whereNotNull('nilai_angka')
                ->avg('nilai_angka');

            if ($rataRata !== null) {
                $skorPerKomponen[] = ['skor' => (float) $rataRata, 'komponen' => $komponen];
            }
        }

        if (empty($skorPerKomponen)) {
            return ['tertinggi' => null, 'terendah' => null];
        }

        $terurutTertinggi = collect($skorPerKomponen)->sortByDesc('skor')->first();
        $terurutTerendah = collect($skorPerKomponen)->sortBy('skor')->first();

        $narasiTertinggi = null;
        $ambangTertinggi = $terurutTertinggi['komponen']->kktp_minimal ?? self::DEFAULT_AMBANG_KKTP;
        if ($terurutTertinggi['skor'] >= $ambangTertinggi) {
            $narasiTertinggi = "Menunjukkan penguasaan sangat baik dalam {$terurutTertinggi['komponen']->deskripsi}.";
        }

        $narasiTerendah = null;
        $ambangTerendah = $terurutTerendah['komponen']->kktp_minimal ?? self::DEFAULT_AMBANG_KKTP;
        if ($terurutTerendah['skor'] < $ambangTerendah) {
            $narasiTerendah = "Perlu bimbingan dan pendampingan dalam {$terurutTerendah['komponen']->deskripsi}.";
        }

        return ['tertinggi' => $narasiTertinggi, 'terendah' => $narasiTerendah];
    }
}
