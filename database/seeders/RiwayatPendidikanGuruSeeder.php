<?php
// database/seeders/RiwayatPendidikanGuruSeeder.php

namespace Database\Seeders;

use App\Models\Guru;
use App\Models\RiwayatPendidikanGuru;
use App\Models\User;
use Illuminate\Database\Seeder;

class RiwayatPendidikanGuruSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'budi.santoso@demo.test' => [
                ['jenjang_pendidikan' => 'S1', 'sekolah_formal' => 'Universitas Pendidikan Indonesia', 'fakultas' => 'FPMIPA', 'bidang_studi' => 'Pendidikan Matematika', 'kependidikan' => true, 'tahun_masuk' => 2003, 'tahun_lulus' => 2007],
            ],
            'siti.rahmawati@demo.test' => [
                ['jenjang_pendidikan' => 'S1', 'sekolah_formal' => 'Universitas Islam Negeri Sunan Gunung Djati', 'fakultas' => 'Tarbiyah', 'bidang_studi' => 'Pendidikan Guru Madrasah Ibtidaiyah', 'kependidikan' => true, 'tahun_masuk' => 2006, 'tahun_lulus' => 2010],
            ],
            'andi.wijaya@demo.test' => [
                ['jenjang_pendidikan' => 'S1', 'sekolah_formal' => 'Institut Agama Islam Negeri Bandung', 'fakultas' => 'Tarbiyah', 'bidang_studi' => 'Pendidikan Agama Islam', 'kependidikan' => true, 'tahun_masuk' => 2008, 'tahun_lulus' => 2012],
            ],
            'hendra.gunawan@demo.test' => [
                ['jenjang_pendidikan' => 'S1', 'sekolah_formal' => 'Institut Teknologi Bandung', 'fakultas' => 'FMIPA', 'bidang_studi' => 'Fisika', 'kependidikan' => false, 'tahun_masuk' => 2000, 'tahun_lulus' => 2004],
                ['jenjang_pendidikan' => 'S2', 'sekolah_formal' => 'Universitas Pendidikan Indonesia', 'fakultas' => 'Sekolah Pascasarjana', 'bidang_studi' => 'Pendidikan Fisika', 'kependidikan' => true, 'tahun_masuk' => 2013, 'tahun_lulus' => 2015],
            ],
            'maya.anggraini@demo.test' => [
                ['jenjang_pendidikan' => 'S1', 'sekolah_formal' => 'Universitas Pendidikan Indonesia', 'fakultas' => 'FPBS', 'bidang_studi' => 'Pendidikan Bahasa Inggris', 'kependidikan' => true, 'tahun_masuk' => 2009, 'tahun_lulus' => 2013],
            ],
            'taufik.hidayat@demo.test' => [
                ['jenjang_pendidikan' => 'S1', 'sekolah_formal' => 'Universitas Pendidikan Indonesia', 'fakultas' => 'FIP', 'bidang_studi' => 'Bimbingan dan Konseling', 'kependidikan' => true, 'tahun_masuk' => 2005, 'tahun_lulus' => 2009],
            ],
        ];

        foreach ($data as $email => $riwayatList) {
            $user = User::where('email', $email)->firstOrFail();
            $guru = Guru::where('user_id', $user->id)->firstOrFail();

            foreach ($riwayatList as $riwayat) {
                RiwayatPendidikanGuru::firstOrCreate(
                    ['guru_id' => $guru->id, 'jenjang_pendidikan' => $riwayat['jenjang_pendidikan']],
                    $riwayat + ['gelar_akademik' => null]
                );
            }
        }
    }
}
