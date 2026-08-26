<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        if (Permission::count() === 0) {
            (new PermissionSeeder())->run();
        }

        $roles = [
            // PLATFORM
            'platform_super_admin' => ['scope_level' => 'platform', 'is_protected' => true],

            // YAYASAN
            'yayasan_super_admin' => ['scope_level' => 'yayasan', 'is_protected' => true],
            'bendahara_yayasan' => ['scope_level' => 'yayasan', 'is_protected' => false],

            // ORGANIZATIONAL SCOPE / PEGAWAI (scope-carrier, lihat spec §5.5)
            'pegawai_lembaga' => ['scope_level' => 'lembaga', 'is_protected' => false],
            'pegawai_yayasan' => ['scope_level' => 'yayasan', 'is_protected' => false],

            // FUNCTIONAL — LEMBAGA
            'kepala_sekolah' => ['scope_level' => 'lembaga', 'is_protected' => false],
            'wakasek_kurikulum' => ['scope_level' => 'lembaga', 'is_protected' => false],
            'wakasek_kesiswaan' => ['scope_level' => 'lembaga', 'is_protected' => false],
            'operator_akademik' => ['scope_level' => 'lembaga', 'is_protected' => false],
            'admin_sdm' => ['scope_level' => 'lembaga', 'is_protected' => false],
            'bendahara_lembaga' => ['scope_level' => 'lembaga', 'is_protected' => false],
            'guru' => ['scope_level' => 'diri_sendiri', 'is_protected' => false],
            'wali_kelas' => ['scope_level' => 'lembaga', 'is_protected' => false],
            'guru_bk' => ['scope_level' => 'lembaga', 'is_protected' => false],
            'admin_sarpras' => ['scope_level' => 'lembaga', 'is_protected' => false],

            // SPMB legacy (dibekukan, spec §9 — TIDAK disentuh RBAC v2 ini)
            'admin_administrasi' => ['scope_level' => 'lembaga', 'is_protected' => false],

            // PRINCIPAL
            'siswa' => ['scope_level' => 'diri_sendiri', 'is_protected' => false],
            'orang_tua' => ['scope_level' => 'diri_sendiri', 'is_protected' => false],
        ];

        foreach ($roles as $name => $attributes) {
            $role = Role::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                $attributes
            );

            if ($name === 'yayasan_super_admin') {
                $role->givePermissionTo(Permission::all());
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

            if ($name === 'bendahara_lembaga') {
                $role->givePermissionTo([
                    'jenis-tagihan.view', 'jenis-tagihan.create', 'jenis-tagihan.edit', 'jenis-tagihan.delete',
                    'tagihan.view', 'tagihan.buat-susulan',
                    'pembayaran.view', 'pembayaran.verifikasi', 'pembayaran.catat-manual', 'pembayaran.virtual-account',
                    'cicilan.kelola',
                    'spmb-pendaftaran.view',
                ]);
            }

            if ($name === 'kepala_sekolah') {
                $role->givePermissionTo([
                    'spmb-pendaftaran.view', 'spmb-pendaftaran.verifikasi-dokumen', 'spmb-pendaftaran.nilai-seleksi',
                    'spmb-pendaftaran.tetapkan-keputusan', 'spmb-pendaftaran.terbitkan-sk',
                    'tagihan.view',
                    'komponen-penilaian.kelola', 'rapor.view', 'rapor.approve',
                    'kenaikan-kelas.kelola',
                    'rpp.view', 'rpp.verify',
                    'kehadiran-sdm.izin.approve',
                ]);
            }

            if ($name === 'guru') {
                $role->givePermissionTo([
                    'presensi.isi', 'asesmen.kelola', 'komponen-penilaian.kelola-sendiri', 'rapor.input-wali', 'rapor.ajukan',
                    'kasus.ajukan', 'kasus.view',
                    'rpp.view', 'rpp.kelola',
                    'kehadiran-sdm.lihat-qr-sendiri',
                    'kehadiran-sdm.izin.ajukan', 'kehadiran-sdm.izin.lihat-sendiri',
                ]);
            }

            if ($name === 'wali_kelas') {
                // Capability gate murni -- assignment kelas mana yang dikelola tetap dari
                // Kelas.wali_kelas_guru_id (relasi domain), BUKAN dari permission ini.
                // Lihat spec §8. Untuk sekarang belum ada permission khusus wali_kelas
                // yang berbeda dari guru biasa -- capability baru ditambah begitu ada
                // fitur nyata yang butuh membedakannya (mis. rapor.input-wali sudah
                // dipegang 'guru' secara umum, cukup untuk saat ini).
            }

            if ($name === 'guru_bk') {
                $role->givePermissionTo([
                    'kasus.view', 'kasus.triase',
                ]);
            }

            if ($name === 'wakasek_kurikulum') {
                $role->givePermissionTo([
                    'komponen-penilaian.kelola', 'rapor.view', 'rapor.verify',
                    'kenaikan-kelas.kelola',
                    'rpp.view', 'rpp.verify',
                    'jadwal-pelajaran.kelola',
                ]);
            }

            if ($name === 'wakasek_kesiswaan') {
                $role->givePermissionTo([
                    'kasus.view', 'kasus.triase', 'kasus.lihat-log-akses',
                ]);
            }

            if ($name === 'operator_akademik') {
                $role->givePermissionTo([
                    'kelas.view', 'kelas.create', 'kelas.edit',
                    'mata-pelajaran.view', 'mata-pelajaran.create', 'mata-pelajaran.edit',
                    'siswa.view', 'siswa.create', 'siswa.edit', 'siswa.spmb-daftar', 'siswa.import',
                    'orang-tua.view', 'orang-tua.create', 'orang-tua.edit',
                    'karyawan.view', 'karyawan.create', 'karyawan.edit',
                    'kasus.view', 'kasus.triase', 'kasus.lihat-log-akses', 'kasus.hapus', 'kasus.pulihkan',
                    'whatsapp-template.edit',
                    'tahun-ajaran.view', 'tahun-ajaran.create', 'tahun-ajaran.activate',
                    'semester.create', 'semester.activate',
                    'pola-jam.view', 'pola-jam.create', 'pola-jam.edit', 'pola-jam.delete',
                    'jam-pelajaran.create', 'jam-pelajaran.edit', 'jam-pelajaran.delete',
                    'jadwal-pelajaran.kelola',
                    'kalender-akademik.view', 'kalender-akademik.kelola',
                    'pengaturan-akademik.kelola',
                    'komponen-penilaian.kelola',
                    'rapor.view', 'rapor.verify',
                    'kenaikan-kelas.kelola',
                    'rpp.view', 'rpp.kelola', 'rpp.verify',
                ]);
            }

            if ($name === 'orang_tua') {
                $role->givePermissionTo([
                    'kasus.ajukan', 'kasus.view', 'kasus.consent',
                    'keuangan.akses',
                ]);
            }

            if ($name === 'siswa') {
                $role->givePermissionTo(['kasus.view']);
            }

            if (in_array($name, ['pegawai_lembaga', 'pegawai_yayasan'], true)) {
                $role->givePermissionTo(['kasus.view', 'kehadiran-sdm.lihat-qr-sendiri', 'kehadiran-sdm.izin.ajukan', 'kehadiran-sdm.izin.lihat-sendiri']);
            }

            if ($name === 'admin_sdm') {
                $role->givePermissionTo([
                    'kehadiran-sdm.view', 'kehadiran-sdm.catat', 'kehadiran-sdm.kelola-konfigurasi', 'kehadiran-sdm.lihat-qr-sendiri',
                    'kehadiran-sdm.izin.approve',
                ]);
            }
        }
    }
}
