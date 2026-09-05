<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Services;

use App\Domains\Akademik\DataTransferObjects\KelengkapanSubjekSel;
use App\Domains\Akademik\DataTransferObjects\RekapNilaiSel;
use App\Domains\Akademik\Enums\AssessmentType;
use App\Domains\Akademik\Enums\JenisAsesmen;
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
        $siswaList = Siswa::where('kelas_id', $kelas->id)->with('person')->orderByNama()->get();

        $asesmenList = Asesmen::where('kelas_id', $kelas->id)
            ->where('semester_id', $semester->id)
            ->whereIn('jenis', JenisAsesmen::masukRapor())
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

    /**
     * Rincian kelengkapan nilai per mata pelajaran/elemen CP untuk satu kelas+semester --
     * BEDA dari hitungRekapKelas(): itu menghitung rata-rata dari nilai yang ADA (siswa
     * dengan 1 dari 3 komponen numeric terisi tetap dianggap "ada nilai"), method ini
     * mendeteksi slot (asesmen x komponen x siswa) yang MASIH KOSONG secara eksplisit --
     * dipakai sebagai panduan kelengkapan sebelum/saat pengajuan rapor, bukan buat cetak.
     *
     * @return Collection<string, KelengkapanSubjekSel> keyed by SubjekPenilaianKey, hanya
     *                                                  berisi subjek yang MASIH ADA siswa belum lengkap (subjek yang sudah 100%
     *                                                  lengkap tidak muncul di hasil).
     */
    public function kelengkapanNilaiKelas(Kelas $kelas, Semester $semester): Collection
    {
        $siswaList = Siswa::where('kelas_id', $kelas->id)->with('person')->orderByNama()->get();

        $asesmenList = Asesmen::where('kelas_id', $kelas->id)
            ->where('semester_id', $semester->id)
            ->whereIn('jenis', JenisAsesmen::masukRapor())
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
            ->get()
            ->keyBy(fn ($n) => "{$n->asesmen_id}-{$n->komponen_penilaian_id}-{$n->siswa_id}");

        $hasil = collect();

        foreach ($subjekList as $key => $subjek) {
            $subjekAsesmen = $asesmenByKey->get($key) ?? collect();

            $slotList = $subjekAsesmen->flatMap(
                fn ($asesmen) => $asesmen->komponenPenilaian->map(fn ($komponen) => ['asesmen_id' => $asesmen->id, 'komponen' => $komponen])
            );

            if ($slotList->isEmpty()) {
                continue;
            }

            $siswaBelumLengkap = $siswaList->filter(function (Siswa $siswa) use ($slotList, $allNilai) {
                return $slotList->contains(function ($slot) use ($siswa, $allNilai) {
                    $nilai = $allNilai->get("{$slot['asesmen_id']}-{$slot['komponen']->id}-{$siswa->id}");

                    return ! $this->isTerisi($nilai, $slot['komponen']->assessment_type);
                });
            })->values();

            if ($siswaBelumLengkap->isNotEmpty()) {
                $hasil->put($key, new KelengkapanSubjekSel($subjek, $siswaList->count(), $siswaBelumLengkap));
            }
        }

        return $hasil;
    }

    /**
     * Persentase ringkas kelengkapan nilai satu kelas+semester -- dipakai widget dashboard
     * (Guru & Lembaga). Sumber perhitungan SAMA dengan kelengkapanNilaiKelas() (tiap slot
     * asesmen x komponen x siswa dicek sesuai assessment_type-nya, bukan cuma numeric),
     * supaya angka di dashboard tidak pernah berbeda dari rincian di halaman
     * pengajuan/verifikasi rapor.
     *
     * @return array{persen: float, terisi: int, total: int}
     */
    public function persentaseKelengkapanKelas(Kelas $kelas, Semester $semester): array
    {
        $totalSiswa = Siswa::where('kelas_id', $kelas->id)->count();

        $asesmenList = Asesmen::where('kelas_id', $kelas->id)
            ->where('semester_id', $semester->id)
            ->whereIn('jenis', JenisAsesmen::masukRapor())
            ->with('komponenPenilaian')
            ->get();

        $slotList = $asesmenList->flatMap(
            fn ($asesmen) => $asesmen->komponenPenilaian->map(fn ($komponen) => ['asesmen_id' => $asesmen->id, 'komponen' => $komponen])
        );

        $totalSlot = $totalSiswa * $slotList->count();

        if ($totalSlot === 0) {
            return ['persen' => 0.0, 'terisi' => 0, 'total' => 0];
        }

        $siswaIds = Siswa::where('kelas_id', $kelas->id)->pluck('id');
        $allNilai = NilaiSiswa::whereIn('asesmen_id', $asesmenList->pluck('id'))
            ->whereIn('siswa_id', $siswaIds)
            ->get()
            ->keyBy(fn ($n) => "{$n->asesmen_id}-{$n->komponen_penilaian_id}-{$n->siswa_id}");

        $terisi = 0;
        foreach ($siswaIds as $siswaId) {
            foreach ($slotList as $slot) {
                $nilai = $allNilai->get("{$slot['asesmen_id']}-{$slot['komponen']->id}-{$siswaId}");
                if ($this->isTerisi($nilai, $slot['komponen']->assessment_type)) {
                    $terisi++;
                }
            }
        }

        return [
            'persen' => round($terisi / $totalSlot * 100, 1),
            'terisi' => $terisi,
            'total' => $totalSlot,
        ];
    }

    private function isTerisi(?NilaiSiswa $nilai, AssessmentType $tipe): bool
    {
        if ($nilai === null) {
            return false;
        }

        return match ($tipe) {
            AssessmentType::Numeric => $nilai->nilai_angka !== null,
            AssessmentType::Predicate => $nilai->predikat !== null,
            AssessmentType::Narrative => trim($nilai->catatan ?? '') !== '',
        };
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
