<?php
// database/seeders/JenisTagihanSeeder.php

namespace Database\Seeders;

use App\Models\JenisTagihan;
use App\Models\Lembaga;
use Illuminate\Database\Seeder;

class JenisTagihanSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Lembaga::whereIn('npsn', ['20223344', '20223355'])->get() as $lembaga) {
            JenisTagihan::firstOrCreate(
                ['lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran'],
                ['bisa_dicicil' => false, 'maks_cicilan' => null]
            );

            JenisTagihan::firstOrCreate(
                ['lembaga_id' => $lembaga->id, 'nama' => 'Uang Pangkal', 'kategori' => 'daftar_ulang'],
                ['bisa_dicicil' => true, 'maks_cicilan' => 3]
            );
        }
    }
}
