<?php

namespace Database\Seeders;

use App\Models\Lembaga;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn(static::class.': dilewati, hanya boleh jalan di environment local/testing.');

            return;
        }

        $kbit = Lembaga::where('npsn', '20223311')->firstOrFail();

        if (! User::where('email', 'adm.yayasan@demo.test')->exists()) {
            $adminYayasan = User::create([
                'name' => 'Ahmad Fauzi (Admin Yayasan)',
                'email' => 'adm.yayasan@demo.test',
                'password' => 'password',
                'yayasan_id' => $kbit->yayasan_id,
                'email_verified_at' => now(),
                'is_active' => true,
            ]);
            $adminYayasan->assignRole('yayasan_super_admin');
        }

        $tkit = Lembaga::where('npsn', '20223322')->firstOrFail();
        $sdit = Lembaga::where('npsn', '20223333')->firstOrFail();
        $smpit = Lembaga::where('npsn', '20223344')->firstOrFail();

        // KBIT
        $this->seedStaf($kbit, [
            ['name' => 'Ustadzah Aisyah, S.Psi.', 'email' => 'kepsek.kb@demo.test', 'role' => 'kepala_sekolah'],
            ['name' => 'Ustadzah Nurul, S.Pd.', 'email' => 'adm.kb@demo.test', 'role' => 'admin_administrasi'],
            ['name' => 'Ustadzah Halimah, S.E.', 'email' => 'keuangan.kb@demo.test', 'role' => 'admin_keuangan'],
        ], [
            ['name' => 'Ustadzah Fatimah, S.Psi.', 'email' => 'guru.kb1@demo.test'],
            ['name' => 'Ustadzah Zahra, S.Pd.', 'email' => 'guru.kb2@demo.test'],
            ['name' => 'Ustadzah Rini, S.Pd.', 'email' => 'guru.kb3@demo.test'],
        ]);

        // TKIT
        $this->seedStaf($tkit, [
            ['name' => 'Ustadzah Maryam, S.Pd.I.', 'email' => 'kepsek.tk@demo.test', 'role' => 'kepala_sekolah'],
            ['name' => 'Ustadzah Indria, S.Pd.', 'email' => 'adm.tk@demo.test', 'role' => 'admin_administrasi'],
            ['name' => 'Ustadzah Khadijah, S.E.', 'email' => 'keuangan.tk@demo.test', 'role' => 'admin_keuangan'],
        ], [
            ['name' => 'Ustadzah Dewi, S.Pd.I.', 'email' => 'guru.tk1@demo.test'],
            ['name' => 'Ustadzah Latifah, S.Pd.', 'email' => 'guru.tk2@demo.test'],
            ['name' => 'Ustadzah Amel, S.Psi.', 'email' => 'guru.tk3@demo.test'],
        ]);

        // SDIT
        $this->seedStaf($sdit, [
            ['name' => 'Ustadz Abdullah, M.Pd.', 'email' => 'kepsek.sd@demo.test', 'role' => 'kepala_sekolah'],
            ['name' => 'Ustadz Lukman, S.Kom.', 'email' => 'adm.sd@demo.test', 'role' => 'admin_administrasi'],
            ['name' => 'Ustadz Hasan, S.E.', 'email' => 'keuangan.sd@demo.test', 'role' => 'admin_keuangan'],
        ], [
            ['name' => 'Hendra Gunawan, S.Pd.', 'email' => 'hendra.gunawan@permata.sch.id'],
            ['name' => 'Maya Anggraini, S.Pd.', 'email' => 'maya.anggraini@permata.sch.id'],
            ['name' => 'Taufik Hidayat, S.Pd.', 'email' => 'taufik.hidayat@permata.sch.id'],
        ]);

        // SMPIT
        $this->seedStaf($smpit, [
            ['name' => 'Ustadz Bambang Suryadi, M.Pd.', 'email' => 'kepsek.smp@demo.test', 'role' => 'kepala_sekolah'],
            ['name' => 'Dewi Lestari, S.Pd.', 'email' => 'adm.smp@demo.test', 'role' => 'admin_administrasi'],
            ['name' => 'Nur Aisyah, S.Pd.', 'email' => 'keuangan.smp@demo.test', 'role' => 'admin_keuangan'],
        ], [
            ['name' => 'Budi Santoso, S.Pd.', 'email' => 'budi.santoso@permata.sch.id'],
            ['name' => 'Siti Rahmawati, S.Pd.', 'email' => 'siti.rahmawati@permata.sch.id'],
            ['name' => 'Andi Wijaya, S.Pd.I.', 'email' => 'andi.wijaya@permata.sch.id'],
        ]);
    }

    private function seedStaf(Lembaga $lembaga, array $pimpinan, array $guruList): void
    {
        foreach ($pimpinan as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => 'password',
                    'lembaga_id' => $lembaga->id,
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]
            );
            $user->assignRole($data['role']);
        }

        foreach ($guruList as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => 'password',
                    'lembaga_id' => $lembaga->id,
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]
            );
            $user->assignRole('guru');
        }
    }
}
