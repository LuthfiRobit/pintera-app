<?php

namespace Database\Seeders;

use App\Models\JenisKaryawanMaster;
use Illuminate\Database\Seeder;

class JenisKaryawanMasterSeeder extends Seeder
{
    public function run(): void
    {
        $jenisKonselor = [
            'Psikolog',
            'Konselor BK',
        ];

        foreach ($jenisKonselor as $nama) {
            JenisKaryawanMaster::firstOrCreate(['nama' => $nama], ['is_konselor' => true]);
        }
    }
}
