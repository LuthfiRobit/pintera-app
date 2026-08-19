<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Services;

use App\Domains\Akademik\Enums\StatusPengajuanRapor;
use App\Domains\Akademik\Models\CatatanWaliKelas;
use App\Domains\Akademik\Models\PengajuanRapor;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;

final class RaporPdfDataBuilder
{
    public function __construct(
        private readonly RaporCalculationService $raporCalculationService,
        private readonly CapaianKompetensiGenerator $capaianKompetensiGenerator,
        private readonly PresensiAggregationService $presensiAggregationService,
    ) {
    }

    /**
     * @return array{
     *   siswa: Siswa, kelas: \App\Models\Kelas, semester: Semester, lembaga: \App\Models\Lembaga,
     *   rekapNilai: array<int, float|null>, mapelList: \Illuminate\Support\Collection,
     *   narasiPerMapel: array<int, array{tertinggi: ?string, terendah: ?string}>,
     *   catatan: ?CatatanWaliKelas,
     *   absensi: array{hadir:int, izin:int, sakit:int, alpa:int, terlambat:int},
     *   pengajuanRapor: ?PengajuanRapor, isDraft: bool,
     *   namaWaliKelas: ?string, namaKepalaSekolah: ?string, namaOrangTua: ?string,
     * }
     */
    public function build(Siswa $siswa, Semester $semester): array
    {
        $kelas = $siswa->kelas;
        $lembaga = $kelas->lembaga;

        $rekap = $this->raporCalculationService->hitungRekapKelas($kelas, $semester);
        $mapelList = $rekap['mapelList'];
        $rekapNilaiSiswa = $rekap['rekapNilai'][$siswa->id] ?? [];

        $narasiPerMapel = [];
        foreach ($mapelList as $mapel) {
            $narasiPerMapel[$mapel->id] = $this->capaianKompetensiGenerator->generateNarasi($siswa, $mapel, $semester);
        }

        $catatan = CatatanWaliKelas::where('siswa_id', $siswa->id)->where('semester_id', $semester->id)->first();

        $absensiSemua = $this->presensiAggregationService->agregasiPerKelas($kelas->id, $semester);
        $absensiSiswa = $absensiSemua->firstWhere('siswa_id', $siswa->id) ?? [
            'hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0, 'terlambat' => 0,
        ];

        $pengajuanRapor = PengajuanRapor::where('kelas_id', $kelas->id)->where('semester_id', $semester->id)->first();
        $isDraft = $pengajuanRapor?->status !== StatusPengajuanRapor::Disetujui;

        $namaWaliKelas = $pengajuanRapor?->diverifikasi_oleh
            ? User::find($pengajuanRapor->diverifikasi_oleh)?->guru?->nama
            : null;
        $namaKepalaSekolah = $pengajuanRapor?->disetujui_oleh
            ? User::find($pengajuanRapor->disetujui_oleh)?->guru?->nama
            : null;
        $namaOrangTua = $siswa->orangTua()->wherePivot('is_kontak_utama', true)->first()?->nama_lengkap;

        return [
            'siswa' => $siswa,
            'kelas' => $kelas,
            'semester' => $semester,
            'lembaga' => $lembaga,
            'rekapNilai' => $rekapNilaiSiswa,
            'mapelList' => $mapelList,
            'narasiPerMapel' => $narasiPerMapel,
            'catatan' => $catatan,
            'absensi' => $absensiSiswa,
            'pengajuanRapor' => $pengajuanRapor,
            'isDraft' => $isDraft,
            'namaWaliKelas' => $namaWaliKelas,
            'namaKepalaSekolah' => $namaKepalaSekolah,
            'namaOrangTua' => $namaOrangTua,
        ];
    }

    /** Whitelist sama seperti field kondisional 04c — literal duplikasi disengaja (YAGNI). */
    public function templateUntukJenjang(string $bentukPendidikan): string
    {
        if (in_array($bentukPendidikan, ['KB', 'TPA', 'SPS', 'TK'], true)) {
            return 'pdf.rapor.paud';
        }

        if ($bentukPendidikan === 'SMK') {
            return 'pdf.rapor.smk';
        }

        if (in_array($bentukPendidikan, ['SMP', 'SMA'], true)) {
            return 'pdf.rapor.smp-sma';
        }

        return 'pdf.rapor.sd';
    }
}
