<?php

// database/seeders/EssentialUserSeeder.php

namespace Database\Seeders;

use App\Domains\Identity\Models\Person;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Database\Seeder;

class EssentialUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn(static::class.': dilewati, hanya boleh jalan di environment local/testing.');

            return;
        }

        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@demo.test'],
            [
                'name' => 'Admin Sistem',
                'password' => 'password',
                'yayasan_id' => Yayasan::first()?->id,
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );
        $superAdmin->assignRole('yayasan_super_admin');

        $lembaga = Lembaga::where('npsn', '20223333')->first() ?? Lembaga::first();

        if (! $lembaga) {
            $this->command?->warn('Belum ada Lembaga -- akun kepala_sekolah/admin_administrasi/bendahara_lembaga/guru dilewati.');

            return;
        }

        $akunLembagaScoped = [
            'kepsek.sd@demo.test' => ['name' => 'Abdullah, M.Pd.', 'role' => 'kepala_sekolah', 'jenis_ptk' => 'kepala_sekolah', 'jenis_kelamin' => 'L', 'nik' => '3273019900010001'],
            'adm.sd@demo.test' => ['name' => 'Lukman, S.Kom.', 'role' => 'admin_administrasi', 'jenis_ptk' => 'tenaga_administrasi', 'jenis_kelamin' => 'L', 'nik' => '3273019900010002'],
            'keuangan.sd@demo.test' => ['name' => 'Hasan, S.E.', 'role' => 'bendahara_lembaga', 'jenis_ptk' => 'tenaga_administrasi', 'jenis_kelamin' => 'L', 'nik' => '3273019900010003'],
            'kurikulum.sd@demo.test' => ['name' => 'Kurikulum (Contoh)', 'role' => 'operator_akademik', 'jenis_ptk' => 'tenaga_administrasi', 'jenis_kelamin' => 'L', 'nik' => '3273019900010004'],
            'guru.sd1@demo.test' => ['name' => 'Sari Wulandari, S.Pd.', 'role' => 'guru', 'jenis_ptk' => 'guru_kelas', 'jenis_kelamin' => 'P', 'nik' => '3273019900010005'],
            'sarpras.sd@demo.test' => ['name' => 'Sarpras (Contoh)', 'role' => 'admin_sarpras', 'jenis_ptk' => 'tenaga_administrasi', 'jenis_kelamin' => 'L', 'nik' => '3273019900010006'],
        ];

        foreach ($akunLembagaScoped as $email => $data) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $data['name'],
                    'password' => 'password',
                    'lembaga_id' => $lembaga->id,
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]
            );
            $user->update(['lembaga_id' => $lembaga->id]);
            $user->assignRole($data['role']);

            // kurikulum.sd@demo.test dobel peran: operator_akademik (kelola data master)
            // DAN wakasek_kurikulum (approver step 1 workflow RAPOR_SEMESTER) -- tanpa ini
            // tidak ada satu pun akun demo yang bisa memverifikasi pengajuan rapor.
            if ($email === 'kurikulum.sd@demo.test') {
                $user->assignRole('wakasek_kurikulum');
            }
            // Baseline scope-carrier (RBAC v2 spec §6.1, §7) -- semua akun di array ini
            // lembaga-affiliated (lembaga_id terisi), jadi pegawai_lembaga, bukan
            // pegawai_yayasan.
            $user->assignRole('pegawai_lembaga');

            $person = Person::withoutGlobalScopes()->where('yayasan_id', $lembaga->yayasan_id)
                ->where('nik_hash', hash('sha256', $data['nik']))
                ->first();

            if (! $person) {
                $person = Person::create([
                    'yayasan_id' => $lembaga->yayasan_id,
                    'user_id' => $user->id,
                    'nik' => $data['nik'],
                    'nama_lengkap' => $data['name'],
                    'jenis_kelamin' => $data['jenis_kelamin'],
                    'kewarganegaraan' => 'WNI',
                    'email' => $email,
                ]);
            } else {
                $person->update(['user_id' => $user->id]);
            }

            // pegawai_lembaga membawa permission self-service SDM (QR kehadiran sendiri,
            // ajukan izin/cuti) yang butuh baris data kepegawaian (tabel `guru`, dipakai
            // juga untuk PTK non-guru via jenis_ptk). Tanpa ini, akun demo di atas kena
            // 404 "Data kepegawaian Anda tidak ditemukan" begitu membuka fitur tsb.
            Guru::firstOrCreate(
                ['person_id' => $person->id],
                [
                    'lembaga_id' => $lembaga->id,
                    'jenis_ptk' => $data['jenis_ptk'],
                    'status_kepegawaian' => 'PTY',
                    'status_aktif' => 'aktif',
                ]
            );
        }
    }
}
