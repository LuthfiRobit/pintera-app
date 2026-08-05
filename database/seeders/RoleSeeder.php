<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'yayasan_super_admin' => ['scope_level' => 'yayasan', 'is_protected' => true],
            'kepala_sekolah' => ['scope_level' => 'lembaga', 'is_protected' => false],
            'admin_administrasi' => ['scope_level' => 'lembaga', 'is_protected' => false],
            'admin_keuangan' => ['scope_level' => 'lembaga', 'is_protected' => false],
            'admin_akademik' => ['scope_level' => 'lembaga', 'is_protected' => false],
            'guru' => ['scope_level' => 'diri_sendiri', 'is_protected' => false],
            'siswa' => ['scope_level' => 'diri_sendiri', 'is_protected' => false],
            'orang_tua' => ['scope_level' => 'diri_sendiri', 'is_protected' => false],
            'karyawan_pool' => ['scope_level' => 'yayasan', 'is_protected' => false],
            'karyawan_lembaga' => ['scope_level' => 'lembaga', 'is_protected' => false],
        ];

        foreach ($roles as $name => $attributes) {
            $role = Role::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                $attributes
            );

            if ($name === 'yayasan_super_admin') {
                $role->syncPermissions(Permission::pluck('name')->all());
            }

            if ($name === 'admin_administrasi') {
                $role->givePermissionTo([
                    'jenis-tes.view', 'jenis-tes.create', 'jenis-tes.edit', 'jenis-tes.delete',
                    'gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit',
                    'jalur-ppdb.view', 'jalur-ppdb.create', 'jalur-ppdb.edit',
                    'formulir-field.create', 'formulir-field.delete',
                    'dokumen-syarat.create', 'dokumen-syarat.delete',
                    'seleksi.create', 'seleksi.delete',
                    'spmb-konfigurasi.duplikasi',
                    'spmb-pendaftaran.view', 'spmb-pendaftaran.verifikasi-dokumen', 'spmb-pendaftaran.nilai-seleksi',
                ]);
            }

            if ($name === 'admin_keuangan') {
                $role->givePermissionTo([
                    'jenis-tagihan.view', 'jenis-tagihan.create', 'jenis-tagihan.edit', 'jenis-tagihan.delete',
                    'tagihan.view', 'tagihan.buat-susulan',
                    'pembayaran.view', 'pembayaran.verifikasi', 'pembayaran.catat-manual',
                    'cicilan.kelola',
                    'spmb-pendaftaran.view',
                ]);
            }

            if ($name === 'kepala_sekolah') {
                $role->givePermissionTo([
                    'spmb-pendaftaran.view', 'spmb-pendaftaran.verifikasi-dokumen', 'spmb-pendaftaran.nilai-seleksi',
                    'spmb-pendaftaran.tetapkan-keputusan', 'spmb-pendaftaran.terbitkan-sk',
                    'tagihan.view',
                    'komponen-penilaian.kelola', 'rapor.view',
                    'kenaikan-kelas.kelola',
                ]);
            }

            if ($name === 'guru') {
                $role->givePermissionTo([
                    'presensi.isi', 'asesmen.kelola', 'komponen-penilaian.kelola-sendiri',
                    'kasus.ajukan', 'kasus.view',
                ]);
            }

            if ($name === 'admin_akademik') {
                $role->givePermissionTo([
                    'kelas.view', 'kelas.create', 'kelas.edit',
                    'mata-pelajaran.view', 'mata-pelajaran.create', 'mata-pelajaran.edit',
                    'siswa.view', 'siswa.create', 'siswa.edit', 'siswa.spmb-daftar', 'siswa.import',
                    'orang-tua.view', 'orang-tua.create', 'orang-tua.edit',
                    'karyawan.view', 'karyawan.create', 'karyawan.edit',
                    'kasus.view', 'kasus.triase',
                    'whatsapp-template.edit',
                    'tahun-ajaran.view', 'tahun-ajaran.create', 'tahun-ajaran.activate',
                    'semester.create', 'semester.activate',
                    'pola-jam.view', 'pola-jam.create', 'pola-jam.edit', 'pola-jam.delete',
                    'jam-pelajaran.create', 'jam-pelajaran.edit', 'jam-pelajaran.delete',
                    'jadwal-pelajaran.kelola',
                    'kalender-akademik.view', 'kalender-akademik.kelola',
                    'pengaturan-akademik.kelola',
                    'komponen-penilaian.kelola',
                    'rapor.view',
                    'kenaikan-kelas.kelola',
                ]);
            }

            if ($name === 'orang_tua') {
                $role->givePermissionTo([
                    'kasus.ajukan', 'kasus.view', 'kasus.consent',
                ]);
            }

            if ($name === 'siswa') {
                $role->givePermissionTo(['kasus.view']);
            }

            if (in_array($name, ['karyawan_pool', 'karyawan_lembaga'], true)) {
                $role->givePermissionTo(['kasus.view']);
            }
        }
    }
}
