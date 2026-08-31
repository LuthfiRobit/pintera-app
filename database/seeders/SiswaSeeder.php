<?php

// database/seeders/SiswaSeeder.php

namespace Database\Seeders;

use App\Domains\Identity\Models\Person;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Database\Seeder;

class SiswaSeeder extends Seeder
{
    private const NAMA_DEPAN = [
        'Ahmad', 'Muhammad', 'Aisyah', 'Fatimah', 'Rizky', 'Putri', 'Bagus', 'Siti', 'Rian', 'Nabila',
        'Fajar', 'Salsabila', 'Dimas', 'Zahra', 'Reza', 'Kirana', 'Fauzan', 'Alya', 'Yusuf', 'Naila',
        'Arif', 'Anisa', 'Bayu', 'Indah', 'Galih', 'Rania', 'Hafiz', 'Keisya', 'Iqbal', 'Maryam',
    ];

    private const NAMA_BELAKANG = [
        'Pratama', 'Santoso', 'Wijaya', 'Hidayat', 'Kurniawan', 'Saputra', 'Anggraini', 'Ramadhan',
        'Lestari', 'Nugroho', 'Firmansyah', 'Utami', 'Setiawan', 'Permata', 'Wibowo', 'Handayani',
    ];

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

            $this->seedGenericStudents($lembaga, $aktif);
            $this->seedSiswaAccount($lembaga);
        }
    }

    private function seedGenericStudents(Lembaga $lembaga, TahunAjaran $aktif): void
    {
        $kelasList = Kelas::where('lembaga_id', $lembaga->id)->where('tahun_ajaran_id', $aktif->id)->get();
        $prefix = substr($lembaga->npsn, -4); // e.g. 3333

        $counter = 1;
        foreach ($kelasList as $kelas) {
            for ($i = 1; $i <= 28; $i++) {
                $numStr = str_pad((string) $counter, 3, '0', STR_PAD_LEFT);
                $nis = $prefix.$numStr;
                $nisn = '00'.$prefix.$numStr;

                $depan = self::NAMA_DEPAN[$counter % count(self::NAMA_DEPAN)];
                $belakang = self::NAMA_BELAKANG[$counter % count(self::NAMA_BELAKANG)];
                $namaLengkap = "{$depan} {$belakang}";
                $jk = ($i % 2 === 1) ? 'L' : 'P';

                $existingSiswa = Siswa::where('lembaga_id', $lembaga->id)->where('nis', $nis)->first();
                if (! $existingSiswa) {
                    $person = Person::create([
                        'yayasan_id' => $lembaga->yayasan_id,
                        'nama_lengkap' => $namaLengkap,
                        'jenis_kelamin' => $jk,
                        'tempat_lahir' => 'Kraksaan',
                        'tanggal_lahir' => '2016-01-10',
                        'agama' => 'Islam',
                    ]);

                    Siswa::create([
                        'person_id' => $person->id,
                        'lembaga_id' => $lembaga->id,
                        'nis' => $nis,
                        'kelas_id' => $kelas->id,
                        'nisn' => $nisn,
                        'sumber_data' => 'manual',
                        'status' => 'aktif',
                    ]);
                }

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
            '20223333' => 'siswa.sd@demo.test',
        ];

        $email = $emailMap[$lembaga->npsn] ?? "siswa.{$lembaga->id}@demo.test";

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

        if ($firstSiswa->person?->user_id !== $user->id) {
            $firstSiswa->person?->update(['user_id' => $user->id]);
        }
    }
}
