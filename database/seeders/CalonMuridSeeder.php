<?php
// database/seeders/CalonMuridSeeder.php

namespace Database\Seeders;

use App\Models\CalonMurid;
use App\Models\Lembaga;
use Illuminate\Database\Seeder;

class CalonMuridSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Lembaga::whereIn('npsn', ['20223344', '20223355'])->get() as $lembaga) {
            $this->seedCalon($lembaga, 'Calon Menunggu Verifikasi', 'L');
            $this->seedCalon($lembaga, 'Calon Diterima', 'L');
            $this->seedCalon($lembaga, 'Calon Ditolak', 'L');
            $this->seedCalon($lembaga, 'Calon Cicilan Demo', 'P');
        }
    }

    private function seedCalon(Lembaga $lembaga, string $namaDasar, string $jenisKelamin): void
    {
        CalonMurid::firstOrCreate(
            ['nama_lengkap' => $namaDasar.' ('.$lembaga->nama.')'],
            [
                'yayasan_id' => $lembaga->yayasan_id,
                'nik' => (string) random_int(3200000000000000, 3299999999999999),
                'jenis_kelamin' => $jenisKelamin,
                'tempat_lahir' => 'Bandung',
                'tanggal_lahir' => now()->subYears(13)->toDateString(),
                'agama' => 'Islam',
            ]
        );
    }
}
