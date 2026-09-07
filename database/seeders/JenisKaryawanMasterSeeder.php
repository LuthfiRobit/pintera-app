<?php

namespace Database\Seeders;

use App\Domains\Sdm\Models\JenisKaryawanMaster;
use App\Models\Yayasan;
use Illuminate\Database\Seeder;

class JenisKaryawanMasterSeeder extends Seeder
{
    public function run(): void
    {
        $yayasanId = Yayasan::value('id');
        if (! $yayasanId) {
            (new YayasanSeeder)->run();
            $yayasanId = Yayasan::value('id') ?? 1;
        }

        $jenisKonselor = [
            'Psikolog',
            'Konselor BK',
        ];

        foreach ($jenisKonselor as $nama) {
            JenisKaryawanMaster::firstOrCreate(
                ['yayasan_id' => $yayasanId, 'nama' => $nama],
                ['is_konselor' => true]
            );
        }

        // Staf umum non-PTK (bukan konselor) -- pola Karyawan yang tepat untuk
        // pegawai di luar tabel Guru/PTK, mis. satpam & cleaning service.
        $jenisStafUmum = [
            'Satpam',
            'Petugas Kebersihan',
        ];

        foreach ($jenisStafUmum as $nama) {
            JenisKaryawanMaster::firstOrCreate(
                ['yayasan_id' => $yayasanId, 'nama' => $nama],
                ['is_konselor' => false]
            );
        }
    }
}
