<?php
// database/seeders/SiswaSeeder.php

namespace Database\Seeders;

use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Database\Seeder;

class SiswaSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn(static::class.': dilewati, hanya boleh jalan di environment local/testing.');

            return;
        }

        foreach (Lembaga::all() as $lembaga) {
            $aktif = TahunAjaran::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first();

            if (! $aktif) {
                continue;
            }

            if ($lembaga->npsn === '20223344') {
                $this->seedSmpStudents($lembaga, $aktif);
            } else {
                $this->seedGenericStudents($lembaga, $aktif);
            }

            $this->seedSiswaAccount($lembaga);
        }
    }

    private function seedSmpStudents(Lembaga $smp, TahunAjaran $aktif): void
    {
        $kelasA = Kelas::where('lembaga_id', $smp->id)->where('tahun_ajaran_id', $aktif->id)->where('nama', 'VII-A')->first();
        $kelasB = Kelas::where('lembaga_id', $smp->id)->where('tahun_ajaran_id', $aktif->id)->where('nama', 'VII-B')->first();

        if ($kelasA) {
            $siswaDataA = [
                ['nis' => '2627001', 'nisn' => '0098765431', 'nama' => 'Aditya Pratama', 'jk' => 'L'],
                ['nis' => '2627002', 'nisn' => '0098765432', 'nama' => 'Budi Setiawan', 'jk' => 'L'],
                ['nis' => '2627003', 'nisn' => '0098765433', 'nama' => 'Citra Lestari', 'jk' => 'P'],
                ['nis' => '2627004', 'nisn' => '0098765434', 'nama' => 'Dedi Wijaya', 'jk' => 'L'],
                ['nis' => '2627005', 'nisn' => '0098765435', 'nama' => 'Eliana Putri', 'jk' => 'P'],
            ];

            foreach ($siswaDataA as $data) {
                Siswa::firstOrCreate(
                    ['lembaga_id' => $smp->id, 'nis' => $data['nis']],
                    [
                        'kelas_id' => $kelasA->id,
                        'nisn' => $data['nisn'],
                        'nama_lengkap' => $data['nama'],
                        'jenis_kelamin' => $data['jk'],
                        'tempat_lahir' => 'Bandung',
                        'tanggal_lahir' => '2014-05-10',
                        'agama' => 'Islam',
                        'sumber_data' => 'manual',
                        'status' => 'aktif',
                    ]
                );
            }
        }

        if ($kelasB) {
            $siswaDataB = [
                ['nis' => '2627006', 'nisn' => '0098765436', 'nama' => 'Fahri Ramadhan', 'jk' => 'L'],
                ['nis' => '2627007', 'nisn' => '0098765437', 'nama' => 'Gita Permata', 'jk' => 'P'],
                ['nis' => '2627008', 'nisn' => '0098765438', 'nama' => 'Hendra Kusuma', 'jk' => 'L'],
                ['nis' => '2627009', 'nisn' => '0098765439', 'nama' => 'Indah Sari', 'jk' => 'P'],
                ['nis' => '2627010', 'nisn' => '0098765440', 'nama' => 'Joko Susilo', 'jk' => 'L'],
            ];

            foreach ($siswaDataB as $data) {
                Siswa::firstOrCreate(
                    ['lembaga_id' => $smp->id, 'nis' => $data['nis']],
                    [
                        'kelas_id' => $kelasB->id,
                        'nisn' => $data['nisn'],
                        'nama_lengkap' => $data['nama'],
                        'jenis_kelamin' => $data['jk'],
                        'tempat_lahir' => 'Bandung',
                        'tanggal_lahir' => '2014-08-15',
                        'agama' => 'Islam',
                        'sumber_data' => 'manual',
                        'status' => 'aktif',
                    ]
                );
            }
        }
    }

    private function seedGenericStudents(Lembaga $lembaga, TahunAjaran $aktif): void
    {
        $kelasList = Kelas::where('lembaga_id', $lembaga->id)->where('tahun_ajaran_id', $aktif->id)->get();
        $prefix = substr($lembaga->npsn, -4); // e.g. 3311, 3322, 3333
        
        $counter = 1;
        foreach ($kelasList as $kelas) {
            for ($i = 1; $i <= 3; $i++) {
                $numStr = str_pad((string) $counter, 3, '0', STR_PAD_LEFT);
                $nis = $prefix . $numStr;
                $nisn = '00' . $prefix . $numStr;

                Siswa::firstOrCreate(
                    ['lembaga_id' => $lembaga->id, 'nis' => $nis],
                    [
                        'kelas_id' => $kelas->id,
                        'nisn' => $nisn,
                        'nama_lengkap' => "Siswa {$kelas->nama} No.{$i}",
                        'jenis_kelamin' => ($i % 2 === 1) ? 'L' : 'P',
                        'tempat_lahir' => 'Kraksaan',
                        'tanggal_lahir' => '2016-01-10',
                        'agama' => 'Islam',
                        'sumber_data' => 'manual',
                        'status' => 'aktif',
                    ]
                );

                $counter++;
            }
        }
    }

    private function seedSiswaAccount(Lembaga $lembaga): void
    {
        $firstSiswa = Siswa::where('lembaga_id', $lembaga->id)->first();
        if (! $firstSiswa) {
            return;
        }

        $emailMap = [
            '20223311' => 'siswa.kbit@permatakraksaan.sch.id',
            '20223322' => 'siswa.tkit@permatakraksaan.sch.id',
            '20223333' => 'siswa.sdit@permatakraksaan.sch.id',
            '20223344' => 'siswa.smpit@permatakraksaan.sch.id',
        ];

        $email = $emailMap[$lembaga->npsn] ?? "siswa.{$lembaga->id}@permatakraksaan.sch.id";

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $firstSiswa->nama_lengkap,
                'password' => 'password',
                'lembaga_id' => $lembaga->id,
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );
        $user->assignRole('siswa');

        if ($firstSiswa->user_id !== $user->id) {
            $firstSiswa->update(['user_id' => $user->id]);
        }
    }
}
