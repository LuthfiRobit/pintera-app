<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PengadaanPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'pengadaan.proposal.create',
            'pengadaan.proposal.view',
            'pengadaan.proposal.edit',
            'pengadaan.proposal.delete',
            'pengadaan.approval.internal',
            'pengadaan.approval.yayasan',
            'pengadaan.disbursement.manage',
            'pengadaan.lpj.submit',
            'pengadaan.lpj.verify',
            'workflow.config.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Assign to Super Admin
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin->givePermissionTo($permissions);

        // Assign to Admin Sarpras Lembaga
        $adminSarpras = Role::firstOrCreate(['name' => 'admin_sarpras', 'guard_name' => 'web']);
        $adminSarpras->givePermissionTo([
            'pengadaan.proposal.create',
            'pengadaan.proposal.view',
            'pengadaan.proposal.edit',
            'pengadaan.proposal.delete',
            'pengadaan.lpj.submit',
        ]);

        // Assign to Kepala Sekolah
        $kepsek = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web']);
        $kepsek->givePermissionTo([
            'pengadaan.proposal.view',
            'pengadaan.approval.internal',
        ]);

        // Assign to Pengurus/Bendahara Yayasan
        $bendaharaYayasan = Role::firstOrCreate(['name' => 'bendahara_yayasan', 'guard_name' => 'web']);
        $bendaharaYayasan->givePermissionTo([
            'pengadaan.proposal.view',
            'pengadaan.approval.yayasan',
            'pengadaan.disbursement.manage',
            'pengadaan.lpj.verify',
        ]);
    }
}
