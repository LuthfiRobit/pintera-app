<?php
// database/seeders/GuruJabatanTambahanSeeder.php

namespace Database\Seeders;

use App\Models\Guru;
use App\Models\GuruJabatanTambahan;
use App\Models\JabatanTambahanMaster;
use App\Models\User;
use Illuminate\Database\Seeder;

class GuruJabatanTambahanSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'siti.rahmawati@alhikmah.sch.id' => ['jabatan' => 'Wali Kelas', 'tmt_tugas' => '2015-07-01'],
            'andi.wijaya@alhikmah.sch.id' => ['jabatan' => 'Pembina Ekstrakurikuler', 'tmt_tugas' => '2019-07-01'],
            'hendra.gunawan@alhikmah.sch.id' => ['jabatan' => 'Wakil Kepala Sekolah Kurikulum', 'tmt_tugas' => '2008-01-01'],
            'taufik.hidayat@alhikmah.sch.id' => ['jabatan' => 'Koordinator BK', 'tmt_tugas' => '2016-07-01'],
        ];

        foreach ($data as $email => $info) {
            $user = User::where('email', $email)->firstOrFail();
            $guru = Guru::where('user_id', $user->id)->firstOrFail();
            $jabatan = JabatanTambahanMaster::where('nama', $info['jabatan'])->firstOrFail();

            GuruJabatanTambahan::firstOrCreate(
                ['guru_id' => $guru->id, 'jabatan_tambahan_master_id' => $jabatan->id],
                [
                    'mulai_periode' => $info['tmt_tugas'],
                    'no_sk' => 'SK.'.random_int(100, 999).'/Yayasan/'.date('Y', strtotime($info['tmt_tugas'])),
                ]
            );
        }
    }
}
