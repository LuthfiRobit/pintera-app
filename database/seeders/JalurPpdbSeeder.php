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
        $smp = Lembaga::where('npsn', '20223344')->firstOrFail();
        $sma = Lembaga::where('npsn', '20223355')->firstOrFail();

        $smpLama = TahunAjaran::where('lembaga_id', $smp->id)->where('status_aktif', false)->firstOrFail();
        $smpBaru = TahunAjaran::where('lembaga_id', $smp->id)->where('status_aktif', true)->firstOrFail();
        $smaBaru = TahunAjaran::where('lembaga_id', $sma->id)->where('status_aktif', true)->firstOrFail();

        $this->seedJalur($smp, $smpLama, $this->jalurSmp());
        $this->seedJalur($smp, $smpBaru, $this->jalurSmp());
        $this->seedJalur($sma, $smaBaru, $this->jalurSma());
    }

    /**
     * @return array<int, array{nama: string, deskripsi: string, status_aktif: bool}>
     */
    public function jalurSmp(): array
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
    public function jalurSma(): array
    {
        return [
            ['nama' => 'Reguler', 'deskripsi' => 'Jalur pendaftaran umum berdasarkan nilai rapor dan hasil tes seleksi.', 'status_aktif' => true],
            ['nama' => 'Prestasi', 'deskripsi' => 'Jalur khusus bagi calon murid dengan prestasi akademik, olahraga, atau seni tingkat kabupaten/kota ke atas.', 'status_aktif' => true],
            ['nama' => 'Afirmasi', 'deskripsi' => 'Jalur bagi calon murid dari keluarga kurang mampu, bebas biaya pendaftaran.', 'status_aktif' => true],
        ];
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
