<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Services;

use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Domains\Akademik\Support\SubjekPenilaianKey;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Siswa;

final class RaporCalculationService
{
    /**
     * @return array{siswaList: \Illuminate\Support\Collection, mapelList: \Illuminate\Support\Collection, rekapNilai: array<int, array<string, float|null>>, classAvg: float|null, highestScore: float|null}
     */
    public function hitungRekapKelas(Kelas $kelas, Semester $semester): array
    {
        $siswaList = Siswa::where('kelas_id', $kelas->id)->orderBy('nama_lengkap')->get();

        $asesmenList = Asesmen::where('kelas_id', $kelas->id)
            ->where('semester_id', $semester->id)
            ->with('subjek')
            ->get();

        $subjekList = $asesmenList->pluck('subjek')
            ->filter()
            ->unique(fn ($s) => SubjekPenilaianKey::dari($s))
            ->sortBy('nama')
            ->values();

        $asesmenByKey = $asesmenList->groupBy(fn ($a) => $a->subjek ? SubjekPenilaianKey::dari($a->subjek) : '');

        $allNilai = NilaiSiswa::whereIn('asesmen_id', $asesmenList->pluck('id'))
            ->with('komponenPenilaian')
            ->get();

        $rekapNilai = [];
        foreach ($siswaList as $siswa) {
            $rekapNilai[$siswa->id] = [];
            foreach ($subjekList as $subjek) {
                $key = SubjekPenilaianKey::dari($subjek);
                $subjekAsesmenIds = ($asesmenByKey->get($key) ?? collect())->pluck('id');
                $scores = $allNilai->whereIn('asesmen_id', $subjekAsesmenIds)
                    ->where('siswa_id', $siswa->id)
                    ->filter(fn ($n) => $n->komponenPenilaian?->assessment_type?->value === 'numeric' && $n->nilai_angka !== null);

                if ($scores->count() > 0) {
                    $totalWeight = 0;
                    $weightedSum = 0;
                    foreach ($scores as $item) {
                        $w = $item->komponenPenilaian && $item->komponenPenilaian->bobot > 0 ? (int) $item->komponenPenilaian->bobot : 1;
                        $weightedSum += ($item->nilai_angka * $w);
                        $totalWeight += $w;
                    }
                    $rekapNilai[$siswa->id][$key] = $totalWeight > 0 ? round($weightedSum / $totalWeight, 1) : null;
                } else {
                    $rekapNilai[$siswa->id][$key] = null;
                }
            }
        }

        $allScores = collect($rekapNilai)->flatMap(fn ($m) => collect($m)->filter(fn ($v) => $v !== null));

        return [
            'siswaList' => $siswaList,
            'mapelList' => $subjekList,
            'rekapNilai' => $rekapNilai,
            'classAvg' => $allScores->count() > 0 ? round($allScores->avg(), 1) : null,
            'highestScore' => $allScores->count() > 0 ? $allScores->max() : null,
        ];
    }
}
