<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class SarprasPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'sarpras.gedung.view',
            'sarpras.gedung.manage',
            'sarpras.ruangan.view',
            'sarpras.ruangan.manage',
            'sarpras.kategori.view',
            'sarpras.kategori.manage',
            'sarpras.aset.view',
            'sarpras.aset.manage',
            'sarpras.mutasi.create',
            'sarpras.mutasi.view',
            'sarpras.kir.export',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Assign to Super Admin
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin->givePermissionTo($permissions);

        // Assign to Admin Sarpras & Admin Administrasi
        $adminSarpras = Role::firstOrCreate(['name' => 'admin_sarpras', 'guard_name' => 'web']);
        $adminSarpras->givePermissionTo($permissions);

        $adminAdm = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web']);
        $adminAdm->givePermissionTo($permissions);

        // Assign to Kepala Sekolah (View)
        $kepsek = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web']);
        $kepsek->givePermissionTo([
            'sarpras.gedung.view',
            'sarpras.ruangan.view',
            'sarpras.kategori.view',
            'sarpras.aset.view',
            'sarpras.mutasi.view',
        ]);

        // Assign to Bendahara Yayasan
        $bendahara = Role::firstOrCreate(['name' => 'bendahara_yayasan', 'guard_name' => 'web']);
        $bendahara->givePermissionTo([
            'sarpras.aset.view',
        ]);
    }
}
