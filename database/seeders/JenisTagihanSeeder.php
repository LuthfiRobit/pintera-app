<?php
// database/seeders/JenisTagihanSeeder.php

namespace Database\Seeders;

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\Lembaga;
use Illuminate\Database\Seeder;

class JenisTagihanSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Lembaga::all() as $lembaga) {
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
