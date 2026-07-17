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
        $smp = Lembaga::where('npsn', '20223344')->firstOrFail();
        $sma = Lembaga::where('npsn', '20223355')->firstOrFail();

        $this->seedTahunAjaran($smp, '2026', '2027');
        $this->seedTahunAjaran($sma, '2026', '2027');
    }

    private function seedTahunAjaran(Lembaga $lembaga, string $tahunAwal, string $tahunAkhir): void
    {
        TahunAjaran::firstOrCreate(
            ['lembaga_id' => $lembaga->id, 'nama' => ($tahunAwal - 1).'/'.$tahunAwal],
            [
                'tanggal_mulai' => ($tahunAwal - 1).'-07-01',
                'tanggal_selesai' => $tahunAwal.'-06-30',
                'status_aktif' => false,
            ]
        );

        TahunAjaran::firstOrCreate(
            ['lembaga_id' => $lembaga->id, 'nama' => $tahunAwal.'/'.$tahunAkhir],
            [
                'tanggal_mulai' => $tahunAwal.'-07-01',
                'tanggal_selesai' => $tahunAkhir.'-06-30',
                'status_aktif' => true,
            ]
        );
    }
}
