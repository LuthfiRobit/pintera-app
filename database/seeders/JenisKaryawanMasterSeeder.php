<?php

namespace Database\Seeders;

use App\Domains\Sdm\Models\JenisKaryawanMaster;
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

        // Staf umum non-PTK (bukan konselor) -- pola Karyawan yang tepat untuk
        // pegawai di luar tabel Guru/PTK, mis. satpam & cleaning service.
        $jenisStafUmum = [
            'Satpam',
            'Petugas Kebersihan',
        ];

        foreach ($jenisStafUmum as $nama) {
            JenisKaryawanMaster::firstOrCreate(['nama' => $nama], ['is_konselor' => false]);
        }
    }
}
