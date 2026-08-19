<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Rapor;

use App\Domains\Akademik\Services\CapaianKompetensiGenerator;
use App\Domains\Akademik\Services\RaporCalculationService;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Siswa;

final class GenerateNarasiPerkembanganAction
{
    public function __construct(
        private readonly RaporCalculationService $raporCalculationService,
        private readonly CapaianKompetensiGenerator $capaianKompetensiGenerator,
    ) {
    }

    /**
     * Gabungkan narasi capaian tertinggi/terendah lintas semua mapel yang diikuti siswa
     * di kelas+semester tsb jadi satu draft paragraf untuk field catatan_perkembangan.
     * String kosong jika kelas tidak punya asesmen sama sekali di semester itu.
     */
    public function execute(Siswa $siswa, Kelas $kelas, Semester $semester): string
    {
        $mapelList = $this->raporCalculationService->hitungRekapKelas($kelas, $semester)['mapelList'];

        $kalimat = [];
        foreach ($mapelList as $mapel) {
            $narasi = $this->capaianKompetensiGenerator->generateNarasi($siswa, $mapel, $semester);
            if ($narasi['tertinggi'] !== null) {
                $kalimat[] = $narasi['tertinggi'];
            }
            if ($narasi['terendah'] !== null) {
                $kalimat[] = $narasi['terendah'];
            }
        }

        return implode(' ', $kalimat);
    }
}
