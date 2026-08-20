<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class RolePermissionAssignmentSeeder extends Seeder
{
    /**
     * Satu-satunya pemilik pivot role_has_permissions. WAJIB jadi entri paling
     * akhir di DatabaseSeeder::run() — yayasan_super_admin di bawah ini
     * mengambil snapshot SEMUA permission yang ada di tabel permissions,
     * jadi harus dijamin seluruh seeder permission lain sudah selesai jalan.
     */
    public function run(): void
    {
        $sarprasPermissions = [
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

        $pengadaanPermissions = [
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

        // ── Sarpras: admin_sarpras & admin_administrasi dapat semua ──────────
        Role::firstOrCreate(['name' => 'admin_sarpras', 'guard_name' => 'web'])
            ->givePermissionTo($sarprasPermissions);
        Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'])
            ->givePermissionTo($sarprasPermissions);

        // ── Sarpras: kepala_sekolah cuma view ─────────────────────────────────
        Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'])
            ->givePermissionTo([
                'sarpras.gedung.view',
                'sarpras.ruangan.view',
                'sarpras.kategori.view',
                'sarpras.aset.view',
                'sarpras.mutasi.view',
            ]);

        // ── Sarpras: bendahara_yayasan cuma lihat aset ────────────────────────
        Role::firstOrCreate(['name' => 'bendahara_yayasan', 'guard_name' => 'web'])
            ->givePermissionTo(['sarpras.aset.view']);

        // ── Pengadaan: admin_sarpras & admin_administrasi (proposal + lpj) ────
        Role::firstOrCreate(['name' => 'admin_sarpras', 'guard_name' => 'web'])
            ->givePermissionTo([
                'pengadaan.proposal.create',
                'pengadaan.proposal.view',
                'pengadaan.proposal.edit',
                'pengadaan.proposal.delete',
                'pengadaan.lpj.submit',
            ]);
        Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'])
            ->givePermissionTo([
                'pengadaan.proposal.create',
                'pengadaan.proposal.view',
                'pengadaan.proposal.edit',
                'pengadaan.proposal.delete',
                'pengadaan.lpj.submit',
            ]);

        // ── Pengadaan: kepala_sekolah (view + approval internal) ──────────────
        Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'])
            ->givePermissionTo([
                'pengadaan.proposal.view',
                'pengadaan.approval.internal',
            ]);

        // ── Pengadaan: bendahara_yayasan (approval yayasan + disbursement) ────
        Role::firstOrCreate(['name' => 'bendahara_yayasan', 'guard_name' => 'web'])
            ->givePermissionTo([
                'pengadaan.proposal.view',
                'pengadaan.approval.yayasan',
                'pengadaan.disbursement.manage',
                'pengadaan.lpj.verify',
            ]);

        // ── Super admin: snapshot LENGKAP semua permission ─────────────────────
        // Aman diambil di sini karena entri ini dijamin paling akhir di
        // DatabaseSeeder::run() — seluruh permission dari seeder lain
        // (termasuk Sarpras/Pengadaan di atas) sudah pasti dibuat sebelum baris
        // ini jalan.
        Role::findByName('yayasan_super_admin')->syncPermissions(Permission::all());
    }
}
