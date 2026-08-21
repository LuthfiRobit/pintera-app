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
            'siti.rahmawati@demo.test' => ['jabatan' => 'Wali Kelas', 'tmt_tugas' => '2015-07-01'],
            'andi.wijaya@demo.test' => ['jabatan' => 'Pembina Ekstrakurikuler', 'tmt_tugas' => '2019-07-01'],
            'hendra.gunawan@demo.test' => ['jabatan' => 'Wakil Kepala Sekolah Kurikulum', 'tmt_tugas' => '2008-01-01'],
            'taufik.hidayat@demo.test' => ['jabatan' => 'Koordinator BK', 'tmt_tugas' => '2016-07-01'],
        ];

        foreach ($data as $email => $info) {
            $user = User::where('email', $email)->first();

            if (! $user) {
                $this->command?->warn("GuruJabatanTambahanSeeder: user {$email} tidak ditemukan (UserSeeder mungkin dilewati di env non-local/testing), dilewati.");

                continue;
            }

            $guru = Guru::where('user_id', $user->id)->first();

            if (! $guru) {
                continue;
            }

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
