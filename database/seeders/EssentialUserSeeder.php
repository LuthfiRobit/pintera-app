<?php
// database/seeders/EssentialUserSeeder.php

namespace Database\Seeders;

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

        $lembaga = Lembaga::where('npsn', '20223344')->first() ?? Lembaga::first();

        if (! $lembaga) {
            $this->command?->warn('Belum ada Lembaga -- akun kepala_sekolah/admin_administrasi/admin_keuangan/guru dilewati.');

            return;
        }

        $akunLembagaScoped = [
            'kepsek.kb@demo.test' => ['name' => 'Kepala Sekolah (Contoh)', 'role' => 'kepala_sekolah'],
            'adm.kb@demo.test' => ['name' => 'Admin Administrasi (Contoh)', 'role' => 'admin_administrasi'],
            'keuangan.kb@demo.test' => ['name' => 'Admin Keuangan (Contoh)', 'role' => 'admin_keuangan'],
            'kurikulum.kb@demo.test' => ['name' => 'Admin Akademik (Contoh)', 'role' => 'admin_akademik'],
            'guru.kb1@demo.test' => ['name' => 'Guru (Contoh)', 'role' => 'guru'],
            'sarpras.kb@demo.test' => ['name' => 'Admin Sarpras (Contoh)', 'role' => 'admin_sarpras'],
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
        }
    }
}
