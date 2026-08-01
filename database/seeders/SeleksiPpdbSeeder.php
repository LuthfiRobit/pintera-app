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
        foreach (Lembaga::all() as $lembaga) {
            $seleksiConfig = match ($lembaga->bentuk_pendidikan) {
                'KB', 'TK' => $this->seleksiPaud(),
                'SD' => $this->seleksiSd(),
                default => $this->seleksiSmp(),
            };

            foreach (TahunAjaran::where('lembaga_id', $lembaga->id)->get() as $tahunAjaran) {
                $this->seedSeleksi($lembaga, $tahunAjaran, $seleksiConfig);
            }
        }
    }

    private function seleksiPaud(): array
    {
        return [
            'Reguler' => [
                ['gelombang' => 'Gelombang 1', 'jenis_tes' => 'Observasi Anak', 'jadwal' => '2026-08-20 08:00:00', 'kriteria' => 'Perkembangan usia sesuai', 'bobot' => 60],
                ['gelombang' => 'Gelombang 1', 'jenis_tes' => 'Wawancara Orang Tua', 'jadwal' => '2026-08-21 08:00:00', 'kriteria' => 'Komitmen pola asuh', 'bobot' => 40],
            ],
            'Prestasi' => [
                ['gelombang' => 'Gelombang 1', 'jenis_tes' => 'Observasi Anak', 'jadwal' => '2026-08-22 09:00:00', 'kriteria' => 'Verifikasi bakat khusus', 'bobot' => 100],
            ],
            'Afirmasi' => [],
        ];
    }

    private function seleksiSd(): array
    {
        return [
            'Reguler' => [
                ['gelombang' => 'Gelombang 1', 'jenis_tes' => 'Observasi Kesiapan Sekolah', 'jadwal' => '2026-08-20 08:00:00', 'kriteria' => 'Kematangan motorik & emosional', 'bobot' => 60],
                ['gelombang' => 'Gelombang 1', 'jenis_tes' => 'Wawancara Orang Tua', 'jadwal' => '2026-08-21 08:00:00', 'kriteria' => 'Komitmen kemitraan orang tua', 'bobot' => 40],
            ],
            'Prestasi' => [
                ['gelombang' => 'Gelombang 1', 'jenis_tes' => 'Tes Baca Al-Qur\'an', 'jadwal' => '2026-08-22 09:00:00', 'kriteria' => 'Kemampuan membaca / tahfizh', 'bobot' => 100],
            ],
            'Afirmasi' => [],
        ];
    }

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
