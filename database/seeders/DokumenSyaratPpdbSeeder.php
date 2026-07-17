<?php
// database/seeders/DokumenSyaratPpdbSeeder.php

namespace Database\Seeders;

use App\Models\DokumenSyaratPpdb;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;

class DokumenSyaratPpdbSeeder extends Seeder
{
    public function run(): void
    {
        $smp = Lembaga::where('npsn', '20223344')->firstOrFail();
        $sma = Lembaga::where('npsn', '20223355')->firstOrFail();

        foreach (TahunAjaran::where('lembaga_id', $smp->id)->get() as $tahunAjaran) {
            $this->seedDokumen($smp, $tahunAjaran, $this->dokumenSmp());
        }

        $smaBaru = TahunAjaran::where('lembaga_id', $sma->id)->where('status_aktif', true)->firstOrFail();
        $this->seedDokumen($sma, $smaBaru, $this->dokumenSma());
    }

    /**
     * @return array<string, array<int, array{nama_dokumen: string, wajib: bool}>>
     */
    private function dokumenSmp(): array
    {
        return [
            'Reguler' => [
                ['nama_dokumen' => 'Akta Kelahiran', 'wajib' => true],
                ['nama_dokumen' => 'Kartu Keluarga', 'wajib' => true],
                ['nama_dokumen' => 'Fotokopi Rapor', 'wajib' => true],
                ['nama_dokumen' => 'Pas Foto 3x4', 'wajib' => true],
            ],
            'Prestasi' => [
                ['nama_dokumen' => 'Akta Kelahiran', 'wajib' => true],
                ['nama_dokumen' => 'Kartu Keluarga', 'wajib' => true],
                ['nama_dokumen' => 'Sertifikat/Piagam Prestasi', 'wajib' => true],
            ],
            'Afirmasi' => [
                ['nama_dokumen' => 'Kartu Keluarga Sejahtera (KKS) / SKTM', 'wajib' => true],
                ['nama_dokumen' => 'Akta Kelahiran', 'wajib' => true],
            ],
        ];
    }

    /**
     * @return array<string, array<int, array{nama_dokumen: string, wajib: bool}>>
     */
    private function dokumenSma(): array
    {
        return [
            'Reguler' => [
                ['nama_dokumen' => 'Ijazah / SKL SMP', 'wajib' => true],
                ['nama_dokumen' => 'Kartu Keluarga', 'wajib' => true],
                ['nama_dokumen' => 'Fotokopi Rapor Kelas VII-IX', 'wajib' => true],
                ['nama_dokumen' => 'Pas Foto 3x4', 'wajib' => true],
            ],
            'Prestasi' => [
                ['nama_dokumen' => 'Ijazah / SKL SMP', 'wajib' => true],
                ['nama_dokumen' => 'Sertifikat/Piagam Prestasi', 'wajib' => true],
            ],
            'Afirmasi' => [
                ['nama_dokumen' => 'Kartu Keluarga Sejahtera (KKS) / SKTM', 'wajib' => true],
                ['nama_dokumen' => 'Ijazah / SKL SMP', 'wajib' => true],
            ],
        ];
    }

    private function seedDokumen(Lembaga $lembaga, TahunAjaran $tahunAjaran, array $dokumenPerJalur): void
    {
        foreach ($dokumenPerJalur as $namaJalur => $dokumenList) {
            $jalur = JalurPpdb::where('lembaga_id', $lembaga->id)->where('tahun_ajaran_id', $tahunAjaran->id)->where('nama', $namaJalur)->first();

            if (! $jalur) {
                continue;
            }

            foreach ($dokumenList as $urutan => $dokumen) {
                DokumenSyaratPpdb::firstOrCreate(
                    ['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => $dokumen['nama_dokumen']],
                    [
                        'lembaga_id' => $lembaga->id,
                        'wajib' => $dokumen['wajib'],
                        'urutan' => $urutan,
                    ]
                );
            }
        }
    }
}
