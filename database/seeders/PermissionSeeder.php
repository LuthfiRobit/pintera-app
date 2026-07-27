<?php
// database/seeders/PermissionSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Legacy flat-name permissions from an earlier RBAC iteration. Matches zero rows on
        // a clean install, so this is a harmless no-op there -- kept so this seeder alone
        // stays safe to run against a database that still has them (mirrors what
        // RolePermissionSeeder used to do, so its own pre-existing regression test keeps
        // passing unmodified).
        Permission::whereIn('name', [
            'manage-roles', 'manage-users', 'manage-yayasan',
            'manage-lembaga', 'manage-tahun-ajaran', 'manage-guru',
            'view-audit-log', 'manage-ppdb',
        ])->delete();

        $permissions = [
            'roles.view', 'roles.create', 'roles.edit', 'roles.delete',
            'users.view', 'users.create', 'users.edit', 'users.toggle-active',
            'lembaga.view', 'lembaga.create', 'lembaga.edit',
            'guru.view', 'guru.create', 'guru.edit',
            'tahun-ajaran.view', 'tahun-ajaran.create', 'tahun-ajaran.activate',
            'semester.create', 'semester.activate',
            'jenis-tes.view', 'jenis-tes.create', 'jenis-tes.edit', 'jenis-tes.delete',
            'gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit',
            'jalur-ppdb.view', 'jalur-ppdb.create', 'jalur-ppdb.edit',
            'formulir-field.create', 'formulir-field.delete',
            'dokumen-syarat.create', 'dokumen-syarat.delete',
            'seleksi.create', 'seleksi.delete',
            'spmb-konfigurasi.duplikasi',
            'audit-log.view',
            'spmb-pendaftaran.view', 'spmb-pendaftaran.verifikasi-dokumen', 'spmb-pendaftaran.nilai-seleksi',
            'spmb-pendaftaran.tetapkan-keputusan', 'spmb-pendaftaran.terbitkan-sk',
            'jenis-tagihan.view', 'jenis-tagihan.create', 'jenis-tagihan.edit', 'jenis-tagihan.delete',
            'tagihan.view', 'tagihan.buat-susulan',
            'pembayaran.view', 'pembayaran.verifikasi', 'pembayaran.catat-manual',
            'cicilan.kelola',
            'presensi.isi', 'asesmen.kelola', 'komponen-penilaian.kelola', 'rapor.view',
            'kenaikan-kelas.kelola',
            'kelas.view', 'kelas.create', 'kelas.edit',
            'mata-pelajaran.view', 'mata-pelajaran.create', 'mata-pelajaran.edit',
            'siswa.view', 'siswa.create', 'siswa.edit', 'siswa.spmb-daftar', 'siswa.import',
            'pola-jam.view', 'pola-jam.create', 'pola-jam.edit', 'pola-jam.delete',
            'jam-pelajaran.create', 'jam-pelajaran.edit', 'jam-pelajaran.delete',
            'jadwal-pelajaran.kelola',
            'kalender-akademik.view', 'kalender-akademik.kelola',
            'pengaturan-akademik.kelola',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }
}
