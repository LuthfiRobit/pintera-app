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
        foreach (Lembaga::all() as $lembaga) {
            $dokumenConfig = match ($lembaga->bentuk_pendidikan) {
                'KB', 'TK' => $this->dokumenPaud(),
                'SD' => $this->dokumenSd(),
                default => $this->dokumenSmp(),
            };

            foreach (TahunAjaran::where('lembaga_id', $lembaga->id)->get() as $tahunAjaran) {
                $this->seedDokumen($lembaga, $tahunAjaran, $dokumenConfig);
            }
        }
    }

    private function dokumenPaud(): array
    {
        return [
            'Reguler' => [
                ['nama_dokumen' => 'Akta Kelahiran', 'wajib' => true],
                ['nama_dokumen' => 'Kartu Keluarga', 'wajib' => true],
                ['nama_dokumen' => 'Pas Foto 3x4', 'wajib' => true],
                ['nama_dokumen' => 'Buku KIA / Kesehatan', 'wajib' => false],
            ],
            'Prestasi' => [
                ['nama_dokumen' => 'Akta Kelahiran', 'wajib' => true],
                ['nama_dokumen' => 'Piagam / Sertifikat Lomba', 'wajib' => true],
            ],
            'Afirmasi' => [
                ['nama_dokumen' => 'Kartu Keluarga Sejahtera (KKS) / SKTM', 'wajib' => true],
                ['nama_dokumen' => 'Akta Kelahiran', 'wajib' => true],
            ],
        ];
    }

    private function dokumenSd(): array
    {
        return [
            'Reguler' => [
                ['nama_dokumen' => 'Akta Kelahiran', 'wajib' => true],
                ['nama_dokumen' => 'Kartu Keluarga', 'wajib' => true],
                ['nama_dokumen' => 'Ijazah / SKL TK / PAUD', 'wajib' => false],
                ['nama_dokumen' => 'Pas Foto 3x4', 'wajib' => true],
            ],
            'Prestasi' => [
                ['nama_dokumen' => 'Akta Kelahiran', 'wajib' => true],
                ['nama_dokumen' => 'Sertifikat/Piagam Prestasi', 'wajib' => true],
            ],
            'Afirmasi' => [
                ['nama_dokumen' => 'Kartu Keluarga Sejahtera (KKS) / SKTM', 'wajib' => true],
                ['nama_dokumen' => 'Akta Kelahiran', 'wajib' => true],
            ],
        ];
    }

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
