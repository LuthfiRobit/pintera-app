<?php
// database/seeders/SeleksiPpdbSeeder.php

namespace Database\Seeders;

use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\JenisTesMaster;
use App\Models\Lembaga;
use App\Models\SeleksiPpdb;
use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;

class SeleksiPpdbSeeder extends Seeder
{
    public function run(): void
    {
        $smp = Lembaga::where('npsn', '20223344')->firstOrFail();
        $sma = Lembaga::where('npsn', '20223355')->firstOrFail();

        foreach (TahunAjaran::where('lembaga_id', $smp->id)->get() as $tahunAjaran) {
            $this->seedSeleksi($smp, $tahunAjaran, $this->seleksiSmp());
        }

        $smaBaru = TahunAjaran::where('lembaga_id', $sma->id)->where('status_aktif', true)->firstOrFail();
        $this->seedSeleksi($sma, $smaBaru, $this->seleksiSma());
    }

    /**
     * @return array<string, array<int, array{gelombang: string, jenis_tes: string, jadwal: string, kriteria: string, bobot: int}>>
     */
    private function seleksiSmp(): array
    {
        return [
            'Reguler' => [
                ['gelombang' => 'Gelombang 1', 'jenis_tes' => 'Tes Tulis', 'jadwal' => '2025-02-20 08:00:00', 'kriteria' => 'Nilai minimal 65', 'bobot' => 60],
                ['gelombang' => 'Gelombang 1', 'jenis_tes' => 'Wawancara', 'jadwal' => '2025-02-21 08:00:00', 'kriteria' => 'Lolos wawancara motivasi', 'bobot' => 40],
            ],
            'Prestasi' => [
                ['gelombang' => 'Gelombang 1', 'jenis_tes' => 'Wawancara', 'jadwal' => '2025-02-22 09:00:00', 'kriteria' => 'Verifikasi keaslian sertifikat & wawancara', 'bobot' => 100],
            ],
            'Afirmasi' => [],
        ];
    }

    /**
     * @return array<string, array<int, array{gelombang: string, jenis_tes: string, jadwal: string, kriteria: string, bobot: int}>>
     */
    private function seleksiSma(): array
    {
        return [
            'Reguler' => [
                ['gelombang' => 'Gelombang 1', 'jenis_tes' => 'Tes Tulis', 'jadwal' => '2026-08-24 08:00:00', 'kriteria' => 'Nilai minimal 70', 'bobot' => 50],
                ['gelombang' => 'Gelombang 1', 'jenis_tes' => 'Tes Potensi Akademik', 'jadwal' => '2026-08-25 08:00:00', 'kriteria' => 'Skor TPA minimal 60', 'bobot' => 50],
            ],
            'Prestasi' => [
                ['gelombang' => 'Gelombang 1', 'jenis_tes' => 'Tes Wawancara', 'jadwal' => '2026-08-26 09:00:00', 'kriteria' => 'Verifikasi keaslian sertifikat & wawancara', 'bobot' => 100],
            ],
            'Afirmasi' => [],
        ];
    }

    private function seedSeleksi(Lembaga $lembaga, TahunAjaran $tahunAjaran, array $seleksiPerJalur): void
    {
        foreach ($seleksiPerJalur as $namaJalur => $seleksiList) {
            $jalur = JalurPpdb::where('lembaga_id', $lembaga->id)->where('tahun_ajaran_id', $tahunAjaran->id)->where('nama', $namaJalur)->first();

            if (! $jalur) {
                continue;
            }

            foreach ($seleksiList as $seleksi) {
                $gelombang = GelombangPpdb::where('lembaga_id', $lembaga->id)->where('tahun_ajaran_id', $tahunAjaran->id)->where('nama', $seleksi['gelombang'])->first();
                $jenisTes = JenisTesMaster::where('lembaga_id', $lembaga->id)->where('nama', $seleksi['jenis_tes'])->first();

                if (! $gelombang || ! $jenisTes) {
                    continue;
                }

                SeleksiPpdb::firstOrCreate(
                    [
                        'jalur_ppdb_id' => $jalur->id,
                        'gelombang_ppdb_id' => $gelombang->id,
                        'jenis_tes_master_id' => $jenisTes->id,
                    ],
                    [
                        'lembaga_id' => $lembaga->id,
                        'jadwal' => $seleksi['jadwal'],
                        'kriteria_kelulusan' => $seleksi['kriteria'],
                        'bobot' => $seleksi['bobot'],
                    ]
                );
            }
        }
    }
}
