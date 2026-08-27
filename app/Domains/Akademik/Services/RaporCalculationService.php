<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Services;

use App\Domains\Akademik\DataTransferObjects\RekapNilaiSel;
use App\Domains\Akademik\Enums\AssessmentType;
use App\Domains\Akademik\Enums\PredikatPaud;
use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Siswa;
use Illuminate\Support\Collection;

final class RaporCalculationService
{
    private const RANKING_PREDIKAT = ['BB' => 1, 'MB' => 2, 'BSH' => 3, 'BSB' => 4];

    /**
     * @return array{siswaList: Collection, mapelList: Collection, rekapNilai: array<int, array<string, ?RekapNilaiSel>>, classAvg: float|null, highestScore: float|null}
     */
    public function hitungRekapKelas(Kelas $kelas, Semester $semester): array
    {
        $siswaList = Siswa::where('kelas_id', $kelas->id)->orderBy('nama_lengkap')->get();

        $asesmenList = Asesmen::where('kelas_id', $kelas->id)
            ->where('semester_id', $semester->id)
            ->with(['subjek', 'komponenPenilaian'])
            ->get();

        $subjekList = $asesmenList->pluck('subjek')
            ->filter()
            ->unique(fn ($s) => SubjekPenilaianKey::dari($s))
            ->sortBy('nama')
            ->keyBy(fn ($s) => SubjekPenilaianKey::dari($s));

        $asesmenByKey = $asesmenList->groupBy(fn ($a) => $a->subjek ? SubjekPenilaianKey::dari($a->subjek) : '');

        $allNilai = NilaiSiswa::whereIn('asesmen_id', $asesmenList->pluck('id'))
            ->with('komponenPenilaian')
            ->get();

        // Total slot narrative per subjek TIDAK bergantung siswa -- dihitung sekali di sini,
        // bukan diulang di dalam loop per siswa.
        $totalNarrativeBySubjek = [];
        foreach ($subjekList as $key => $subjek) {
            $subjekAsesmen = $asesmenByKey->get($key) ?? collect();
            $totalNarrativeBySubjek[$key] = $subjekAsesmen
                ->flatMap(fn ($a) => $a->komponenPenilaian->filter(fn ($k) => $k->assessment_type === AssessmentType::Narrative))
                ->count();
        }

        $rekapNilai = [];
        $rekapNumericMentah = [];

        foreach ($siswaList as $siswa) {
            $rekapNilai[$siswa->id] = [];
            $rekapNumericMentah[$siswa->id] = [];

            foreach ($subjekList as $key => $subjek) {
                $subjekAsesmenIds = ($asesmenByKey->get($key) ?? collect())->pluck('id');
                $nilaiSubjek = $allNilai->whereIn('asesmen_id', $subjekAsesmenIds)->where('siswa_id', $siswa->id);

                $sel = $this->resolveNumeric($nilaiSubjek)
                    ?? $this->resolvePredicate($nilaiSubjek)
                    ?? $this->resolveNarrative($nilaiSubjek, $totalNarrativeBySubjek[$key]);

                $rekapNilai[$siswa->id][$key] = $sel;

                if ($sel !== null && $sel->assessmentType === AssessmentType::Numeric) {
                    $rekapNumericMentah[$siswa->id][$key] = (float) $sel->label;
                }
            }
        }

        $allNumeric = collect($rekapNumericMentah)->flatMap(fn ($m) => collect($m));

        return [
            'siswaList' => $siswaList,
            'mapelList' => $subjekList,
            'rekapNilai' => $rekapNilai,
            'classAvg' => $allNumeric->count() > 0 ? round($allNumeric->avg(), 1) : null,
            'highestScore' => $allNumeric->count() > 0 ? $allNumeric->max() : null,
        ];
    }

    private function resolveNumeric(Collection $nilaiSubjek): ?RekapNilaiSel
    {
        $numericNilai = $nilaiSubjek->filter(
            fn ($n) => $n->komponenPenilaian?->assessment_type === AssessmentType::Numeric && $n->nilai_angka !== null
        );

        if ($numericNilai->count() === 0) {
            return null;
        }

        $totalWeight = 0;
        $weightedSum = 0;
        foreach ($numericNilai as $item) {
            $w = $item->komponenPenilaian && $item->komponenPenilaian->bobot > 0 ? (int) $item->komponenPenilaian->bobot : 1;
            $weightedSum += ($item->nilai_angka * $w);
            $totalWeight += $w;
        }

        if ($totalWeight === 0) {
            return null;
        }

        $nilaiMentah = round($weightedSum / $totalWeight, 1);

        return new RekapNilaiSel(
            assessmentType: AssessmentType::Numeric,
            label: (string) $nilaiMentah,
            tuntas: $nilaiMentah >= config('akademik.ambang_tuntas'),
        );
    }

    private function resolvePredicate(Collection $nilaiSubjek): ?RekapNilaiSel
    {
        $predicateNilai = $nilaiSubjek->filter(
            fn ($n) => $n->komponenPenilaian?->assessment_type === AssessmentType::Predicate && $n->predikat !== null
        );

        if ($predicateNilai->count() === 0) {
            return null;
        }

        $frekuensi = [];
        foreach ($predicateNilai as $item) {
            $kode = $item->predikat instanceof PredikatPaud ? $item->predikat->value : (string) $item->predikat;
            $frekuensi[$kode] = ($frekuensi[$kode] ?? 0) + 1;
        }

        $terpilih = null;
        $terbanyak = -1;
        foreach ($frekuensi as $kode => $jumlah) {
            if ($jumlah > $terbanyak || ($jumlah === $terbanyak && self::RANKING_PREDIKAT[$kode] > self::RANKING_PREDIKAT[$terpilih])) {
                $terpilih = $kode;
                $terbanyak = $jumlah;
            }
        }

        return new RekapNilaiSel(
            assessmentType: AssessmentType::Predicate,
            label: $terpilih,
            tuntas: null,
        );
    }

    private function resolveNarrative(Collection $nilaiSubjek, int $total): ?RekapNilaiSel
    {
        if ($total === 0) {
            return null;
        }

        $terisi = $nilaiSubjek->filter(
            fn ($n) => $n->komponenPenilaian?->assessment_type === AssessmentType::Narrative && trim($n->catatan ?? '') !== ''
        )->count();

        return new RekapNilaiSel(
            assessmentType: AssessmentType::Narrative,
            label: "{$terisi}/{$total}",
            tuntas: null,
        );
    }
}
