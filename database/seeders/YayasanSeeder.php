<?php

namespace Database\Seeders;

use App\Models\Yayasan;
use Illuminate\Database\Seeder;

class YayasanSeeder extends Seeder
{
    public function run(): void
    {
        Yayasan::firstOrCreate(
            ['nama' => 'Yayasan Pintera'],
            [
                'npwp_yayasan' => '01.234.567.8-901.000',
                'akta_pendirian_nomor' => '12',
                'akta_pendirian_tanggal' => '2005-03-15',
                'sk_kemenkumham_nomor' => 'AHU-0001234.AH.01.04.Tahun 2005',
                'alamat' => 'Jl. Panglima Sudirman No. 88, Kraksaan, Kabupaten Probolinggo, Jawa Timur 67282',
                'telepon' => '0335-771234',
                'email' => 'info@pintera.sch.id',
                'website' => 'https://pintera.sch.id',
                'nama_ketua_pembina' => 'KH. Ahmad Fauzi, Lc., M.Pd.',
                'nama_ketua_pengurus' => 'Hj. Siti Maryam, S.Ag.',
            ]
        );
    }
}
