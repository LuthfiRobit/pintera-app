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
        foreach (Lembaga::all() as $lembaga) {
            $this->seedCalon($lembaga, 'Calon Menunggu Verifikasi', 'L');
            $this->seedCalon($lembaga, 'Calon Diterima', 'L');
            $this->seedCalon($lembaga, 'Calon Ditolak', 'L');
            $this->seedCalon($lembaga, 'Calon Cicilan Demo', 'P');
        }
    }

    private function seedCalon(Lembaga $lembaga, string $namaDasar, string $jenisKelamin): void
    {
        $umur = match ($lembaga->bentuk_pendidikan) {
            'KB', 'TK' => 4,
            'SD' => 7,
            default => 13,
        };

        CalonMurid::firstOrCreate(
            ['nama_lengkap' => $namaDasar.' ('.$lembaga->nama.')'],
            [
                'yayasan_id' => $lembaga->yayasan_id,
                'nik' => '0000'.str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT),
                'jenis_kelamin' => $jenisKelamin,
                'tempat_lahir' => 'Bandung',
                'tanggal_lahir' => now()->subYears($umur)->toDateString(),
                'agama' => 'Islam',
            ]
        );
    }
}
