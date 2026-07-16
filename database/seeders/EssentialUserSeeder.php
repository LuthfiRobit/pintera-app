<?php
// database/seeders/EssentialUserSeeder.php

namespace Database\Seeders;

use App\Models\Lembaga;
use App\Models\User;
use Illuminate\Database\Seeder;

class EssentialUserSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@sistem.test'],
            [
                'name' => 'Admin Sistem',
                'password' => 'password',
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );
        $superAdmin->assignRole('yayasan_super_admin');

        $lembaga = Lembaga::first();

        if (! $lembaga) {
            $this->command?->warn('Belum ada Lembaga -- akun kepala_sekolah/admin_administrasi/admin_keuangan/guru dilewati.');

            return;
        }

        $akunLembagaScoped = [
            'kepsek@sistem.test' => ['name' => 'Kepala Sekolah (Contoh)', 'role' => 'kepala_sekolah'],
            'adm@sistem.test' => ['name' => 'Admin Administrasi (Contoh)', 'role' => 'admin_administrasi'],
            'keuangan@sistem.test' => ['name' => 'Admin Keuangan (Contoh)', 'role' => 'admin_keuangan'],
            'guru@sistem.test' => ['name' => 'Guru (Contoh)', 'role' => 'guru'],
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
            $user->assignRole($data['role']);
        }
    }
}
