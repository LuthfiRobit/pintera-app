<?php

namespace Database\Seeders;

use App\Models\Yayasan;
use Illuminate\Database\Seeder;

class YayasanSeeder extends Seeder
{
    public function run(): void
    {
        Yayasan::firstOrCreate(
            ['nama' => 'Yayasan Pendidikan Islam Al-Hikmah'],
            [
                'npwp_yayasan' => '01.234.567.8-901.000',
                'akta_pendirian_nomor' => '12',
                'akta_pendirian_tanggal' => '2005-03-15',
                'sk_kemenkumham_nomor' => 'AHU-0001234.AH.01.04.Tahun 2005',
                'alamat' => 'Jl. Pendidikan Raya No. 45, Kel. Sukaluyu, Kec. Cibeunying Kaler, Kota Bandung, Jawa Barat 40123',
                'telepon' => '022-7301234',
                'email' => 'info@alhikmah.sch.id',
                'website' => 'https://alhikmah.sch.id',
                'nama_ketua_pembina' => 'Dr. H. Ahmad Fauzi, M.Pd.',
                'nama_ketua_pengurus' => 'Hj. Siti Maryam, S.Ag.',
            ]
        );
    }
}
