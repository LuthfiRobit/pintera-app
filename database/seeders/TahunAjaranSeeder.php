<?php
// database/seeders/TahunAjaranSeeder.php

namespace Database\Seeders;

use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;

class TahunAjaranSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Lembaga::all() as $lembaga) {
            $this->seedTahunAjaran($lembaga, '2025', '2026', false);
            $this->seedTahunAjaran($lembaga, '2026', '2027', true);
        }
    }

    private function seedTahunAjaran(Lembaga $lembaga, string $tahunAwal, string $tahunAkhir, bool $aktif): void
    {
        TahunAjaran::firstOrCreate(
            ['lembaga_id' => $lembaga->id, 'nama' => $tahunAwal.'/'.$tahunAkhir],
            [
                'tanggal_mulai' => $tahunAwal.'-07-01',
                'tanggal_selesai' => $tahunAkhir.'-06-30',
                'status_aktif' => $aktif,
            ]
        );
    }
}
