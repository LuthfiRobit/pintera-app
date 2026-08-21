<?php
// database/seeders/SertifikasiGuruSeeder.php

namespace Database\Seeders;

use App\Models\Guru;
use App\Models\SertifikasiGuru;
use App\Models\User;
use Illuminate\Database\Seeder;

class SertifikasiGuruSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'budi.santoso@demo.test' => ['jenis_sertifikasi' => 'Sertifikasi Guru (Portofolio)', 'nomor_sertifikat' => '123456789012', 'bidang_studi_sertifikasi' => 'Matematika', 'nrg' => '112233445566', 'tahun_sertifikasi' => 2012],
            'hendra.gunawan@demo.test' => ['jenis_sertifikasi' => 'Sertifikasi Guru (PLPG)', 'nomor_sertifikat' => '223456789012', 'bidang_studi_sertifikasi' => 'Fisika', 'nrg' => '122334455667', 'tahun_sertifikasi' => 2010],
            'maya.anggraini@demo.test' => ['jenis_sertifikasi' => 'Sertifikasi Guru (PPG Dalam Jabatan)', 'nomor_sertifikat' => '323456789012', 'bidang_studi_sertifikasi' => 'Bahasa Inggris', 'nrg' => '132334455668', 'tahun_sertifikasi' => 2022],
        ];

        foreach ($data as $email => $sertifikasi) {
            $user = User::where('email', $email)->first();

            if (! $user) {
                $this->command?->warn("SertifikasiGuruSeeder: user {$email} tidak ditemukan (UserSeeder mungkin dilewati di env non-local/testing), dilewati.");

                continue;
            }

            $guru = Guru::where('user_id', $user->id)->first();

            if (! $guru) {
                continue;
            }

            SertifikasiGuru::firstOrCreate(
                ['guru_id' => $guru->id, 'jenis_sertifikasi' => $sertifikasi['jenis_sertifikasi']],
                $sertifikasi
            );
        }
    }
}
