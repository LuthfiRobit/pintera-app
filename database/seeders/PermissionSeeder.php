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
            'karyawan.view', 'karyawan.create', 'karyawan.edit',
            'jabatan-tambahan-master.view', 'jabatan-tambahan-master.create', 'jabatan-tambahan-master.edit', 'jabatan-tambahan-master.delete',
            'jenis-karyawan-master.view', 'jenis-karyawan-master.create', 'jenis-karyawan-master.edit', 'jenis-karyawan-master.delete',
            'whatsapp-template.edit',
            'kasus.ajukan', 'kasus.view', 'kasus.triase', 'kasus.consent',
            'kasus.lihat-log-akses', 'kasus.hapus', 'kasus.pulihkan',
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
            'pembayaran.view', 'pembayaran.verifikasi', 'pembayaran.catat-manual', 'pembayaran.virtual-account',
            'cicilan.kelola',
            'keuangan.akses',
            'yayasan.kelola',
            'presensi.isi', 'asesmen.kelola', 'komponen-penilaian.kelola', 'komponen-penilaian.kelola-sendiri', 'rapor.view',
            'rapor.input-wali', 'rapor.ajukan', 'rapor.verify', 'rapor.approve',
            'kenaikan-kelas.kelola',
            'kelas.view', 'kelas.create', 'kelas.edit',
            'fase-mapping.view', 'fase-mapping.create', 'fase-mapping.edit', 'fase-mapping.delete',
            'kurikulum-assignment.view', 'kurikulum-assignment.create', 'kurikulum-assignment.edit', 'kurikulum-assignment.delete',
            'mata-pelajaran.view', 'mata-pelajaran.create', 'mata-pelajaran.edit',
            'siswa.view', 'siswa.create', 'siswa.edit', 'siswa.spmb-daftar', 'siswa.import',
            'orang-tua.view', 'orang-tua.create', 'orang-tua.edit',
            'siswa-keringanan.kelola',
            'pola-jam.view', 'pola-jam.create', 'pola-jam.edit', 'pola-jam.delete',
            'jam-pelajaran.create', 'jam-pelajaran.edit', 'jam-pelajaran.delete',
            'jadwal-pelajaran.kelola',
            'kalender-akademik.view', 'kalender-akademik.kelola', 'kalender-akademik.kelola-nasional',
            'pengaturan-akademik.kelola',
            // Sarpras (dipindah dari SarprasPermissionSeeder.php, dihapus — lihat Task 1 §3 spec)
            'sarpras.gedung.view', 'sarpras.gedung.manage',
            'sarpras.ruangan.view', 'sarpras.ruangan.manage',
            'sarpras.kategori.view', 'sarpras.kategori.manage',
            'sarpras.aset.view', 'sarpras.aset.manage',
            'sarpras.mutasi.create', 'sarpras.mutasi.view',
            'sarpras.kir.export',
            // Pengadaan (dipindah dari PengadaanPermissionSeeder.php, dihapus — lihat Task 1 §3 spec)
            'pengadaan.proposal.create', 'pengadaan.proposal.view', 'pengadaan.proposal.edit', 'pengadaan.proposal.delete',
            'pengadaan.approval.internal', 'pengadaan.approval.yayasan',
            'pengadaan.disbursement.manage',
            'pengadaan.lpj.submit', 'pengadaan.lpj.verify',
            'workflow.config.manage',
            'rpp.view', 'rpp.kelola', 'rpp.verify',
            'kehadiran-sdm.view', 'kehadiran-sdm.catat', 'kehadiran-sdm.kelola-konfigurasi', 'kehadiran-sdm.lihat-qr-sendiri',
            'kehadiran-sdm.izin.ajukan', 'kehadiran-sdm.izin.approve', 'kehadiran-sdm.izin.lihat-sendiri',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }
}
