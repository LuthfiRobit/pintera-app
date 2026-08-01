<?php

namespace Database\Seeders;

use App\Models\Lembaga;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (! User::where('email', 'admin.yayasan@permatakraksaan.sch.id')->exists()) {
            $adminYayasan = User::create([
                'name' => 'Ahmad Fauzi (Admin Yayasan)',
                'email' => 'admin.yayasan@permatakraksaan.sch.id',
                'password' => 'password',
                'email_verified_at' => now(),
                'is_active' => true,
            ]);
            $adminYayasan->assignRole('yayasan_super_admin');
        }

        $kbit = Lembaga::where('npsn', '20223311')->firstOrFail();
        $tkit = Lembaga::where('npsn', '20223322')->firstOrFail();
        $sdit = Lembaga::where('npsn', '20223333')->firstOrFail();
        $smpit = Lembaga::where('npsn', '20223344')->firstOrFail();

        // KBIT
        $this->seedStaf($kbit, [
            ['name' => 'Ustadzah Aisyah, S.Psi.', 'email' => 'kepsek.kbit@permatakraksaan.sch.id', 'role' => 'kepala_sekolah'],
            ['name' => 'Ustadzah Nurul, S.Pd.', 'email' => 'adm.kbit@permatakraksaan.sch.id', 'role' => 'admin_administrasi'],
            ['name' => 'Ustadzah Halimah, S.E.', 'email' => 'keuangan.kbit@permatakraksaan.sch.id', 'role' => 'admin_keuangan'],
        ], [
            ['name' => 'Ustadzah Fatimah, S.Psi.', 'email' => 'guru.kbit1@permatakraksaan.sch.id'],
            ['name' => 'Ustadzah Zahra, S.Pd.', 'email' => 'guru.kbit2@permatakraksaan.sch.id'],
            ['name' => 'Ustadzah Rini, S.Pd.', 'email' => 'guru.kbit3@permatakraksaan.sch.id'],
        ]);

        // TKIT
        $this->seedStaf($tkit, [
            ['name' => 'Ustadzah Maryam, S.Pd.I.', 'email' => 'kepsek.tkit@permatakraksaan.sch.id', 'role' => 'kepala_sekolah'],
            ['name' => 'Ustadzah Indria, S.Pd.', 'email' => 'adm.tkit@permatakraksaan.sch.id', 'role' => 'admin_administrasi'],
            ['name' => 'Ustadzah Khadijah, S.E.', 'email' => 'keuangan.tkit@permatakraksaan.sch.id', 'role' => 'admin_keuangan'],
        ], [
            ['name' => 'Ustadzah Dewi, S.Pd.I.', 'email' => 'guru.tkit1@permatakraksaan.sch.id'],
            ['name' => 'Ustadzah Latifah, S.Pd.', 'email' => 'guru.tkit2@permatakraksaan.sch.id'],
            ['name' => 'Ustadzah Amel, S.Psi.', 'email' => 'guru.tkit3@permatakraksaan.sch.id'],
        ]);

        // SDIT
        $this->seedStaf($sdit, [
            ['name' => 'Ustadz Abdullah, M.Pd.', 'email' => 'kepsek.sdit@permatakraksaan.sch.id', 'role' => 'kepala_sekolah'],
            ['name' => 'Ustadz Lukman, S.Kom.', 'email' => 'adm.sdit@permatakraksaan.sch.id', 'role' => 'admin_administrasi'],
            ['name' => 'Ustadz Hasan, S.E.', 'email' => 'keuangan.sdit@permatakraksaan.sch.id', 'role' => 'admin_keuangan'],
        ], [
            ['name' => 'Hendra Gunawan, S.Pd.', 'email' => 'hendra.gunawan@permata.sch.id'],
            ['name' => 'Maya Anggraini, S.Pd.', 'email' => 'maya.anggraini@permata.sch.id'],
            ['name' => 'Taufik Hidayat, S.Pd.', 'email' => 'taufik.hidayat@permata.sch.id'],
        ]);

        // SMPIT
        $this->seedStaf($smpit, [
            ['name' => 'Ustadz Bambang Suryadi, M.Pd.', 'email' => 'kepsek.smpit@permatakraksaan.sch.id', 'role' => 'kepala_sekolah'],
            ['name' => 'Dewi Lestari, S.Pd.', 'email' => 'adm.smpit@permatakraksaan.sch.id', 'role' => 'admin_administrasi'],
            ['name' => 'Nur Aisyah, S.Pd.', 'email' => 'keuangan.smpit@permatakraksaan.sch.id', 'role' => 'admin_keuangan'],
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
