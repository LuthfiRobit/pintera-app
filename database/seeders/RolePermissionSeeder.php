<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'roles.view', 'roles.create', 'roles.edit', 'roles.delete',
            'users.view', 'users.create', 'users.edit', 'users.toggle-active',
            'lembaga.view', 'lembaga.create', 'lembaga.edit',
            'guru.view', 'guru.create', 'guru.edit',
            'tahun-ajaran.view', 'tahun-ajaran.create', 'tahun-ajaran.activate',
            'semester.create', 'semester.activate',
            'jenis-tes.view', 'jenis-tes.create', 'jenis-tes.delete',
            'gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit',
            'jalur-ppdb.view', 'jalur-ppdb.create', 'jalur-ppdb.edit',
            'formulir-field.create', 'formulir-field.delete',
            'dokumen-syarat.create', 'dokumen-syarat.delete',
            'seleksi.create', 'seleksi.delete',
            'spmb-konfigurasi.duplikasi',
            'audit-log.view',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $roles = [
            'yayasan_super_admin' => ['scope_level' => 'yayasan', 'is_protected' => true],
            'kepala_sekolah' => ['scope_level' => 'lembaga', 'is_protected' => false],
            'admin_administrasi' => ['scope_level' => 'lembaga', 'is_protected' => false],
            'admin_keuangan' => ['scope_level' => 'lembaga', 'is_protected' => false],
            'guru' => ['scope_level' => 'diri_sendiri', 'is_protected' => false],
        ];

        foreach ($roles as $name => $attributes) {
            $role = Role::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                $attributes
            );

            if ($name === 'yayasan_super_admin') {
                $role->syncPermissions($permissions);
            }

            if ($name === 'admin_administrasi') {
                $role->givePermissionTo([
                    'jenis-tes.view', 'jenis-tes.create', 'jenis-tes.delete',
                    'gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit',
                    'jalur-ppdb.view', 'jalur-ppdb.create', 'jalur-ppdb.edit',
                    'formulir-field.create', 'formulir-field.delete',
                    'dokumen-syarat.create', 'dokumen-syarat.delete',
                    'seleksi.create', 'seleksi.delete',
                    'spmb-konfigurasi.duplikasi',
                ]);
            }
        }
    }
}
