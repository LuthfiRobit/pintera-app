<?php

namespace Database\Seeders;

use App\Models\Lembaga;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (! User::where('email', 'admin.yayasan@alhikmah.sch.id')->exists()) {
            $adminYayasan = User::create([
                'name' => 'Ahmad Fauzi (Admin Yayasan)',
                'email' => 'admin.yayasan@alhikmah.sch.id',
                'password' => 'password',
                'email_verified_at' => now(),
                'is_active' => true,
            ]);
            $adminYayasan->assignRole('yayasan_super_admin');
        }

        $smp = Lembaga::where('npsn', '20223344')->firstOrFail();
        $sma = Lembaga::where('npsn', '20223355')->firstOrFail();

        $this->seedStaf($smp, [
            ['name' => 'Drs. H. Bambang Suryadi, M.Pd.', 'email' => 'kepsek.smp@alhikmah.sch.id', 'role' => 'kepala_sekolah'],
            ['name' => 'Dewi Lestari, S.Pd.', 'email' => 'adm.smp@alhikmah.sch.id', 'role' => 'admin_administrasi'],
            ['name' => 'Nur Aisyah, S.Pd.', 'email' => 'keuangan.smp@alhikmah.sch.id', 'role' => 'admin_keuangan'],
        ], [
            ['name' => 'Budi Santoso, S.Pd.', 'email' => 'budi.santoso@alhikmah.sch.id'],
            ['name' => 'Siti Rahmawati, S.Pd.', 'email' => 'siti.rahmawati@alhikmah.sch.id'],
            ['name' => 'Andi Wijaya, S.Pd.I.', 'email' => 'andi.wijaya@alhikmah.sch.id'],
        ]);

        $this->seedStaf($sma, [
            ['name' => 'Dr. Hj. Ratna Dewi, M.M.Pd.', 'email' => 'kepsek.sma@alhikmah.sch.id', 'role' => 'kepala_sekolah'],
            ['name' => 'Rizal Firmansyah, S.Kom.', 'email' => 'adm.sma@alhikmah.sch.id', 'role' => 'admin_administrasi'],
            ['name' => 'Fajar Ramadhan, S.E.', 'email' => 'keuangan.sma@alhikmah.sch.id', 'role' => 'admin_keuangan'],
        ], [
            ['name' => 'Hendra Gunawan, S.Pd.', 'email' => 'hendra.gunawan@alhikmah.sch.id'],
            ['name' => 'Maya Anggraini, S.Pd.', 'email' => 'maya.anggraini@alhikmah.sch.id'],
            ['name' => 'Taufik Hidayat, S.Pd.', 'email' => 'taufik.hidayat@alhikmah.sch.id'],
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
