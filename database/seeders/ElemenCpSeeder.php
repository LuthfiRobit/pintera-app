<?php

namespace Database\Seeders;

use App\Domains\Akademik\Models\ElemenCp;
use Illuminate\Database\Seeder;

class ElemenCpSeeder extends Seeder
{
    public function run(): void
    {
        $elemen = [
            ['kode' => 'nilai_agama_moral', 'nama' => 'Nilai Agama dan Budi Pekerti', 'no_urut' => 1],
            ['kode' => 'jati_diri', 'nama' => 'Jati Diri', 'no_urut' => 2],
            ['kode' => 'literasi_steam', 'nama' => 'Literasi, STEAM, Seni, dan Budaya', 'no_urut' => 3],
        ];

        foreach ($elemen as $data) {
            ElemenCp::firstOrCreate(['kode' => $data['kode']], $data);
        }
    }
}
