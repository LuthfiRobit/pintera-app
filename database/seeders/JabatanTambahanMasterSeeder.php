<?php

namespace Database\Seeders;

use App\Domains\Sdm\Models\JabatanTambahanMaster;
use Illuminate\Database\Seeder;

class JabatanTambahanMasterSeeder extends Seeder
{
    public function run(): void
    {
        $struktural = [
            'Wakil Kepala Sekolah Kurikulum',
            'Wakil Kepala Sekolah Kesiswaan',
            'Wakil Kepala Sekolah Sarpras',
            'Wakil Kepala Sekolah Humas',
            'Kepala Perpustakaan',
            'Kepala Laboratorium',
            'Kepala Program Keahlian',
            'Koordinator BK',
        ];

        $fungsional = [
            'Wali Kelas',
            'Guru Wali',
            'Pembina OSIS',
            'Pembina Ekstrakurikuler',
            'Koordinator Pengembangan Kompetensi',
            'Koordinator Pembelajaran Berbasis Projek (P5)',
            'Koordinator/Anggota TPPK',
            'Guru Pendidikan Khusus (GPK) / Pembimbing Khusus',
        ];

        foreach ($struktural as $nama) {
            JabatanTambahanMaster::firstOrCreate(['nama' => $nama], ['kelompok' => 'struktural']);
        }

        foreach ($fungsional as $nama) {
            JabatanTambahanMaster::firstOrCreate(['nama' => $nama], ['kelompok' => 'fungsional']);
        }
    }
}
