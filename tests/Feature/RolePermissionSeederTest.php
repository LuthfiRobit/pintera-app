<?php

use App\Models\Role;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Permission;

it('seeds the initial permissions', function () {
    (new RolePermissionSeeder)->run();

    $expected = [
        'roles.view', 'roles.create', 'roles.edit', 'roles.delete',
        'users.view', 'users.create', 'users.edit', 'users.toggle-active',
        'lembaga.view', 'lembaga.create', 'lembaga.edit',
        'jenis-karyawan-master.view', 'jenis-karyawan-master.create', 'jenis-karyawan-master.edit', 'jenis-karyawan-master.delete',
        'guru.view', 'guru.create', 'guru.edit',
        'karyawan.view', 'karyawan.create', 'karyawan.edit',
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
        'presensi.isi', 'asesmen.kelola', 'komponen-penilaian.kelola', 'rapor.view',
        'rapor.input-wali', 'rapor.ajukan', 'rapor.verify', 'rapor.approve',
        'kenaikan-kelas.kelola',
        'kelas.view', 'kelas.create', 'kelas.edit',
        'kurikulum-assignment.view', 'kurikulum-assignment.create', 'kurikulum-assignment.edit', 'kurikulum-assignment.delete',
        'mata-pelajaran.view', 'mata-pelajaran.create', 'mata-pelajaran.edit',
        'siswa.view', 'siswa.create', 'siswa.edit', 'siswa.spmb-daftar', 'siswa.import',
        'siswa-keringanan.kelola',
        'pola-jam.view', 'pola-jam.create', 'pola-jam.edit', 'pola-jam.delete',
        'jam-pelajaran.create', 'jam-pelajaran.edit', 'jam-pelajaran.delete',
        'jadwal-pelajaran.kelola',
        'kalender-akademik.view', 'kalender-akademik.kelola', 'kalender-akademik.kelola-nasional',
        'pengaturan-akademik.kelola',
        'rpp.view', 'rpp.kelola', 'rpp.verify',
        'kehadiran-sdm.izin.ajukan', 'kehadiran-sdm.izin.approve', 'kehadiran-sdm.izin.lihat-sendiri',
    ];

    foreach ($expected as $name) {
        expect(Permission::where('name', $name)->exists())->toBeTrue();
    }

    expect(Permission::count())->toBe(151);
});

it('seeds the initial roles with correct scope and protection', function () {
    (new RolePermissionSeeder)->run();

    $superAdmin = Role::where('name', 'yayasan_super_admin')->first();
    expect($superAdmin->scope_level)->toBe('yayasan');
    expect($superAdmin->is_protected)->toBeTrue();
    expect($superAdmin->permissions()->count())->toBe(151);

    expect(Role::where('name', 'kepala_sekolah')->first()->scope_level)->toBe('lembaga');
    expect(Role::where('name', 'admin_administrasi')->first()->scope_level)->toBe('lembaga');
    expect(Role::where('name', 'bendahara_lembaga')->first()->scope_level)->toBe('lembaga');
    expect(Role::where('name', 'guru')->first()->scope_level)->toBe('diri_sendiri');
    expect(Role::where('name', 'operator_akademik')->first()->scope_level)->toBe('lembaga');
    expect(Role::where('name', 'bendahara_yayasan')->first()->scope_level)->toBe('yayasan');
    expect(Role::where('name', 'admin_sarpras')->first()->scope_level)->toBe('lembaga');
    expect(Role::where('name', 'admin_sdm')->first()->scope_level)->toBe('lembaga');
});

it('gives admin_administrasi the SPMB-related granular permissions by default', function () {
    (new RolePermissionSeeder)->run();

    $adminAdministrasi = Role::where('name', 'admin_administrasi')->first();
    $expected = [
        'jenis-tes.view', 'jenis-tes.create', 'jenis-tes.edit', 'jenis-tes.delete',
        'gelombang-ppdb.view', 'gelombang-ppdb.create', 'gelombang-ppdb.edit',
        'jalur-ppdb.view', 'jalur-ppdb.create', 'jalur-ppdb.edit',
        'formulir-field.create', 'formulir-field.delete',
        'dokumen-syarat.create', 'dokumen-syarat.delete',
        'seleksi.create', 'seleksi.delete',
        'spmb-konfigurasi.duplikasi',
        'spmb-pendaftaran.view', 'spmb-pendaftaran.verifikasi-dokumen', 'spmb-pendaftaran.nilai-seleksi',
    ];

    foreach ($expected as $name) {
        expect($adminAdministrasi->hasPermissionTo($name))->toBeTrue();
    }
    expect($adminAdministrasi->permissions()->count())->toBe(20);
});

it('gives kepala_sekolah all five spmb-pendaftaran permissions by default, including tetapkan-keputusan and terbitkan-sk', function () {
    (new RolePermissionSeeder)->run();

    $kepalaSekolah = Role::where('name', 'kepala_sekolah')->first();
    $expected = [
        'spmb-pendaftaran.view', 'spmb-pendaftaran.verifikasi-dokumen', 'spmb-pendaftaran.nilai-seleksi',
        'spmb-pendaftaran.tetapkan-keputusan', 'spmb-pendaftaran.terbitkan-sk',
        'tagihan.view',
        'komponen-penilaian.kelola', 'rapor.view', 'rapor.approve',
        'kenaikan-kelas.kelola',
        'rpp.view', 'rpp.verify',
        'kehadiran-sdm.izin.approve',
    ];

    foreach ($expected as $name) {
        expect($kepalaSekolah->hasPermissionTo($name))->toBeTrue();
    }
    expect($kepalaSekolah->permissions()->count())->toBe(13);
});

it('gives bendahara_lembaga the jenis-tagihan and tagihan permissions by default', function () {
    (new RolePermissionSeeder)->run();

    $bendahara = Role::where('name', 'bendahara_lembaga')->first();
    $expected = [
        'jenis-tagihan.view', 'jenis-tagihan.create', 'jenis-tagihan.edit', 'jenis-tagihan.delete',
        'tagihan.view', 'tagihan.buat-susulan',
        'pembayaran.view', 'pembayaran.verifikasi', 'pembayaran.catat-manual', 'pembayaran.virtual-account',
        'cicilan.kelola',
        'spmb-pendaftaran.view',
        'siswa-keringanan.kelola',
    ];

    foreach ($expected as $name) {
        expect($bendahara->hasPermissionTo($name))->toBeTrue();
    }
    expect($bendahara->permissions()->count())->toBe(14);
});

it('is idempotent when run twice', function () {
    (new RolePermissionSeeder)->run();
    (new RolePermissionSeeder)->run();

    expect(Role::count())->toBe(18);
    expect(Permission::count())->toBe(151);
});

it('removes orphaned old flat permission rows on re-seed', function () {
    Permission::firstOrCreate(['name' => 'manage-guru', 'guard_name' => 'web']);

    (new RolePermissionSeeder)->run();

    expect(Permission::where('name', 'manage-guru')->exists())->toBeFalse();
});

it('gives operator_akademik the kasus audit-log and soft-delete permissions', function () {
    (new RolePermissionSeeder)->run();

    $operatorAkademik = Role::where('name', 'operator_akademik')->first();
    foreach (['kasus.lihat-log-akses', 'kasus.hapus', 'kasus.pulihkan'] as $name) {
        expect($operatorAkademik->hasPermissionTo($name))->toBeTrue();
    }
});
