<?php
// database/seeders/JalurPpdbSeeder.php

namespace Database\Seeders;

use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;

class JalurPpdbSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Lembaga::all() as $lembaga) {
            foreach (TahunAjaran::where('lembaga_id', $lembaga->id)->get() as $tahunAjaran) {
                $this->seedJalur($lembaga, $tahunAjaran, $this->jalurUmum());
            }
        }
    }

    /**
     * @return array<int, array{nama: string, deskripsi: string, status_aktif: bool}>
     */
    public function jalurUmum(): array
    {
        return [
            ['nama' => 'Reguler', 'deskripsi' => 'Jalur pendaftaran umum berdasarkan urutan pendaftaran dan kelengkapan berkas.', 'status_aktif' => true],
            ['nama' => 'Prestasi', 'deskripsi' => 'Jalur khusus bagi calon murid dengan prestasi akademik atau non-akademik.', 'status_aktif' => true],
            ['nama' => 'Afirmasi', 'deskripsi' => 'Jalur bagi calon murid dari keluarga kurang mampu, bebas biaya pendaftaran.', 'status_aktif' => true],
        ];
    }

    /**
     * @return array<int, array{nama: string, deskripsi: string, status_aktif: bool}>
     */
    public function jalurSmp(): array
    {
        return $this->jalurUmum();
    }

    private function seedJalur(Lembaga $lembaga, TahunAjaran $tahunAjaran, array $jalurConfig): void
    {
        foreach ($jalurConfig as $j) {
            JalurPpdb::firstOrCreate(
                ['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => $j['nama']],
                [
                    'deskripsi' => $j['deskripsi'],
                    'status_aktif' => $j['status_aktif'],
                ]
            );
        }
    }
}
