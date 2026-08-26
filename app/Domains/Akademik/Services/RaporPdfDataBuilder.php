<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Services;

use App\Domains\Akademik\Enums\StatusPengajuanRapor;
use App\Domains\Akademik\Models\CatatanWaliKelas;
use App\Domains\Akademik\Models\PengajuanRapor;
use App\Domains\Akademik\Services\AcademicProfile;
use App\Domains\Akademik\Services\SubjekPenilaianKey;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use LogicException;

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
     *   rekapNilai: array<string, float|null>, mapelList: \Illuminate\Support\Collection,
     *   narasiPerMapel: array<string, array{tertinggi: ?string, terendah: ?string}>,
     *   catatan: ?CatatanWaliKelas,
     *   absensi: array{hadir:int, izin:int, sakit:int, alpa:int, terlambat:int},
     *   pengajuanRapor: ?PengajuanRapor, isDraft: bool,
     *   namaWaliKelas: ?string, namaKepalaSekolah: ?string, namaOrangTua: ?string,
     *   isGenap: bool, isTingkatAkhir: bool, labelKenaikan: string, judulDokumen: string,
     *   absensiTahunan: ?array{hadir:int, izin:int, sakit:int, alpa:int, terlambat:int},
     *   nilaiRataRataTahunan: ?array<string, float|null>,
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
            $narasiPerMapel[SubjekPenilaianKey::dari($mapel)] = $this->capaianKompetensiGenerator->generateNarasi($siswa, $mapel, $semester);
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

        $isGenap = $semester->urutan === 2;
        $isTingkatAkhir = $this->isTingkatAkhir($lembaga->bentuk_pendidikan, $kelas->tingkat);
        $labelKenaikan = ($isGenap && $isTingkatAkhir) ? 'Keterangan Kelulusan' : 'Keterangan Kenaikan Kelas';
        $judulDokumen = "Laporan Hasil Belajar Semester {$semester->nama} — {$kelas->tahunAjaran->nama}";

        $absensiTahunan = null;
        $nilaiRataRataTahunan = null;

        if ($isGenap) {
            $semesterGanjil = Semester::where('tahun_ajaran_id', $semester->tahun_ajaran_id)->where('urutan', 1)->first();

            if ($semesterGanjil) {
                $absensiGanjilSemua = $this->presensiAggregationService->agregasiPerKelas($kelas->id, $semesterGanjil);
                $absensiGanjilSiswa = $absensiGanjilSemua->firstWhere('siswa_id', $siswa->id) ?? [
                    'hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0, 'terlambat' => 0,
                ];

                $absensiTahunan = [
                    'hadir' => $absensiSiswa['hadir'] + $absensiGanjilSiswa['hadir'],
                    'izin' => $absensiSiswa['izin'] + $absensiGanjilSiswa['izin'],
                    'sakit' => $absensiSiswa['sakit'] + $absensiGanjilSiswa['sakit'],
                    'alpa' => $absensiSiswa['alpa'] + $absensiGanjilSiswa['alpa'],
                    'terlambat' => $absensiSiswa['terlambat'] + $absensiGanjilSiswa['terlambat'],
                ];

                $rekapGanjil = $this->raporCalculationService->hitungRekapKelas($kelas, $semesterGanjil);
                $rekapNilaiGanjilSiswa = $rekapGanjil['rekapNilai'][$siswa->id] ?? [];

                $nilaiRataRataTahunan = [];
                foreach ($mapelList as $mapel) {
                    $key = SubjekPenilaianKey::dari($mapel);
                    $nilaiGenap = $rekapNilaiSiswa[$key] ?? null;
                    $nilaiGanjil = $rekapNilaiGanjilSiswa[$key] ?? null;

                    $nilaiRataRataTahunan[$key] = match (true) {
                        $nilaiGenap !== null && $nilaiGanjil !== null => round(($nilaiGenap + $nilaiGanjil) / 2, 1),
                        $nilaiGenap !== null => $nilaiGenap,
                        $nilaiGanjil !== null => $nilaiGanjil,
                        default => null,
                    };
                }
            }
        }

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
            'isGenap' => $isGenap,
            'isTingkatAkhir' => $isTingkatAkhir,
            'labelKenaikan' => $labelKenaikan,
            'judulDokumen' => $judulDokumen,
            'absensiTahunan' => $absensiTahunan,
            'nilaiRataRataTahunan' => $nilaiRataRataTahunan,
        ];
    }

    private function isTingkatAkhir(?string $bentukPendidikan, ?string $tingkat): bool
    {
        $tingkatAkhirPerJenjang = [
            'SD' => '6',
            'SLB' => '6',
            'SMP' => '9',
            'SMA' => '12',
            'SMK' => '12',
        ];

        return isset($tingkatAkhirPerJenjang[$bentukPendidikan]) && $tingkatAkhirPerJenjang[$bentukPendidikan] === $tingkat;
    }

    public function templateUntukJenjang(string $bentukPendidikan): string
    {
        return match (AcademicProfile::fromBentukPendidikan($bentukPendidikan)->reportTemplate) {
            'paud' => 'pdf.rapor.paud',
            'sd' => 'pdf.rapor.sd',
            'smp-sma' => 'pdf.rapor.smp-sma',
            'smk' => 'pdf.rapor.smk',
            // Defense-in-depth: AcademicProfile saat ini hanya mengembalikan 4 key
            // di atas (dibuktikan test AcademicProfileTest, Sprint 4). Branch ini
            // TIDAK bisa dites lewat unit test biasa (tidak ada cara alami membuat
            // reportTemplate mengembalikan key ke-5 tanpa mengubah AcademicProfile
            // itu sendiri) -- itu SAH, bukan dead code yang harus dihapus.
            default => throw new LogicException('Unsupported academic report template key.'),
        };
    }
}
