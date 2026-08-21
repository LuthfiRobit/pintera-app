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
            ['name' => 'Aisyah, S.Psi.', 'email' => 'kepsek.kb@demo.test', 'role' => 'kepala_sekolah'],
            ['name' => 'Nurul, S.Pd.', 'email' => 'adm.kb@demo.test', 'role' => 'admin_administrasi'],
            ['name' => 'Halimah, S.E.', 'email' => 'keuangan.kb@demo.test', 'role' => 'admin_keuangan'],
        ], [
            ['name' => 'Fatimah, S.Psi.', 'email' => 'guru.kb1@demo.test'],
            ['name' => 'Zahra, S.Pd.', 'email' => 'guru.kb2@demo.test'],
            ['name' => 'Rini, S.Pd.', 'email' => 'guru.kb3@demo.test'],
        ]);

        // TKIT
        $this->seedStaf($tkit, [
            ['name' => 'Maryam, S.Pd.I.', 'email' => 'kepsek.tk@demo.test', 'role' => 'kepala_sekolah'],
            ['name' => 'Indria, S.Pd.', 'email' => 'adm.tk@demo.test', 'role' => 'admin_administrasi'],
            ['name' => 'Khadijah, S.E.', 'email' => 'keuangan.tk@demo.test', 'role' => 'admin_keuangan'],
        ], [
            ['name' => 'Dewi, S.Pd.I.', 'email' => 'guru.tk1@demo.test'],
            ['name' => 'Latifah, S.Pd.', 'email' => 'guru.tk2@demo.test'],
            ['name' => 'Amel, S.Psi.', 'email' => 'guru.tk3@demo.test'],
        ]);

        // SDIT
        $this->seedStaf($sdit, [
            ['name' => 'Abdullah, M.Pd.', 'email' => 'kepsek.sd@demo.test', 'role' => 'kepala_sekolah'],
            ['name' => 'Lukman, S.Kom.', 'email' => 'adm.sd@demo.test', 'role' => 'admin_administrasi'],
            ['name' => 'Hasan, S.E.', 'email' => 'keuangan.sd@demo.test', 'role' => 'admin_keuangan'],
        ], [
            ['name' => 'Hendra Gunawan, S.Pd.', 'email' => 'hendra.gunawan@demo.test'],
            ['name' => 'Maya Anggraini, S.Pd.', 'email' => 'maya.anggraini@demo.test'],
            ['name' => 'Taufik Hidayat, S.Pd.', 'email' => 'taufik.hidayat@demo.test'],
        ]);

        // SMPIT
        $this->seedStaf($smpit, [
            ['name' => 'Bambang Suryadi, M.Pd.', 'email' => 'kepsek.smp@demo.test', 'role' => 'kepala_sekolah'],
            ['name' => 'Dewi Lestari, S.Pd.', 'email' => 'adm.smp@demo.test', 'role' => 'admin_administrasi'],
            ['name' => 'Nur Aisyah, S.Pd.', 'email' => 'keuangan.smp@demo.test', 'role' => 'admin_keuangan'],
        ], [
            ['name' => 'Budi Santoso, S.Pd.', 'email' => 'budi.santoso@demo.test'],
            ['name' => 'Siti Rahmawati, S.Pd.', 'email' => 'siti.rahmawati@demo.test'],
            ['name' => 'Andi Wijaya, S.Pd.I.', 'email' => 'andi.wijaya@demo.test'],
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
            $user->update(['name' => $data['name'], 'lembaga_id' => $lembaga->id]);
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
            $user->update(['name' => $data['name'], 'lembaga_id' => $lembaga->id]);
            $user->assignRole('guru');
        }
    }
}
