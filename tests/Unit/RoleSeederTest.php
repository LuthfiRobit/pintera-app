<?php

use App\Models\Role;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionAssignmentSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new PermissionSeeder)->run();
});

it('seeds roles with correct scope and protection', function () {
    (new RoleSeeder)->run();

    $superAdmin = Role::where('name', 'yayasan_super_admin')->first();
    expect($superAdmin->scope_level)->toBe('yayasan');
    expect($superAdmin->is_protected)->toBeTrue();
    expect($superAdmin->permissions()->count())->toBe(149);

    expect(Role::where('name', 'kepala_sekolah')->first()->scope_level)->toBe('lembaga');
    expect(Role::where('name', 'admin_administrasi')->first()->scope_level)->toBe('lembaga');
    expect(Role::where('name', 'bendahara_lembaga')->first()->scope_level)->toBe('lembaga');
    expect(Role::where('name', 'guru')->first()->scope_level)->toBe('diri_sendiri');
    expect(Role::where('name', 'operator_akademik')->first()->scope_level)->toBe('lembaga');
    expect(Role::where('name', 'admin_sdm')->first()->scope_level)->toBe('lembaga');
    expect(Role::where('name', 'platform_super_admin')->first()->scope_level)->toBe('platform');
    expect(Role::where('name', 'pegawai_lembaga')->first()->scope_level)->toBe('lembaga');
    expect(Role::where('name', 'pegawai_yayasan')->first()->scope_level)->toBe('yayasan');
});

it('gives admin_administrasi the correct 20 SPMB-related permissions', function () {
    (new RoleSeeder)->run();

    $adminAdministrasi = Role::where('name', 'admin_administrasi')->first();
    expect($adminAdministrasi->permissions()->count())->toBe(20);
    expect($adminAdministrasi->hasPermissionTo('jalur-ppdb.create'))->toBeTrue();
});

it('gives kepala_sekolah the correct 13 permissions', function () {
    (new RoleSeeder)->run();

    $kepalaSekolah = Role::where('name', 'kepala_sekolah')->first();
    expect($kepalaSekolah->permissions()->count())->toBe(13);
    expect($kepalaSekolah->hasPermissionTo('spmb-pendaftaran.tetapkan-keputusan'))->toBeTrue();
    expect($kepalaSekolah->hasPermissionTo('komponen-penilaian.kelola'))->toBeTrue();
    expect($kepalaSekolah->hasPermissionTo('rapor.view'))->toBeTrue();
    expect($kepalaSekolah->hasPermissionTo('rapor.approve'))->toBeTrue();
    expect($kepalaSekolah->hasPermissionTo('kenaikan-kelas.kelola'))->toBeTrue();
    expect($kepalaSekolah->hasPermissionTo('rpp.view'))->toBeTrue();
    expect($kepalaSekolah->hasPermissionTo('rpp.verify'))->toBeTrue();
    expect($kepalaSekolah->hasPermissionTo('kehadiran-sdm.izin.approve'))->toBeTrue();
});

it('gives bendahara_lembaga the correct 12 permissions', function () {
    (new RoleSeeder)->run();

    $bendahara = Role::where('name', 'bendahara_lembaga')->first();
    expect($bendahara->permissions()->count())->toBe(12);
    expect($bendahara->hasPermissionTo('cicilan.kelola'))->toBeTrue();
    expect($bendahara->hasPermissionTo('pembayaran.virtual-account'))->toBeTrue();
});

it('gives guru the presensi, asesmen, and komponen-penilaian-sendiri permissions', function () {
    (new RoleSeeder)->run();

    $guru = Role::where('name', 'guru')->first();
    expect($guru->permissions()->count())->toBe(12);
    expect($guru->hasPermissionTo('presensi.isi'))->toBeTrue();
    expect($guru->hasPermissionTo('asesmen.kelola'))->toBeTrue();
    expect($guru->hasPermissionTo('komponen-penilaian.kelola-sendiri'))->toBeTrue();
    expect($guru->hasPermissionTo('rapor.input-wali'))->toBeTrue();
    expect($guru->hasPermissionTo('rapor.ajukan'))->toBeTrue();
    expect($guru->hasPermissionTo('rpp.view'))->toBeTrue();
    expect($guru->hasPermissionTo('rpp.kelola'))->toBeTrue();
    expect($guru->hasPermissionTo('kehadiran-sdm.lihat-qr-sendiri'))->toBeTrue();
    expect($guru->hasPermissionTo('kehadiran-sdm.izin.ajukan'))->toBeTrue();
    expect($guru->hasPermissionTo('kehadiran-sdm.izin.lihat-sendiri'))->toBeTrue();
});

it('is idempotent when run twice', function () {
    (new RoleSeeder)->run();
    (new RoleSeeder)->run();

    expect(Role::count())->toBe(18);
});

it('grants kalender-akademik.kelola-nasional to yayasan_super_admin via bulk permission sync, but not to operator_akademik', function () {
    (new RoleSeeder)->run();

    expect(Permission::where('name', 'kalender-akademik.kelola-nasional')->exists())->toBeTrue();

    $superAdmin = Role::where('name', 'yayasan_super_admin')->firstOrFail();
    expect($superAdmin->hasPermissionTo('kalender-akademik.kelola-nasional'))->toBeTrue();

    $operatorAkademik = Role::where('name', 'operator_akademik')->firstOrFail();
    expect($operatorAkademik->hasPermissionTo('kalender-akademik.kelola-nasional'))->toBeFalse();
});

it('also gets the full permission set re-synced by RolePermissionAssignmentSeeder at the end of the full seed chain', function () {
    (new RoleSeeder)->run();
    (new RolePermissionAssignmentSeeder)->run();

    $superAdmin = Role::where('name', 'yayasan_super_admin')->firstOrFail();
    expect($superAdmin->permissions()->count())->toBe(Permission::count());
});

it('grants kenaikan-kelas.kelola to kepala_sekolah after permissions sync and role seeding', function () {
    Artisan::call('permissions:sync');
    (new RoleSeeder)->run();

    $kepalaSekolah = Role::where('name', 'kepala_sekolah')->firstOrFail();

    expect($kepalaSekolah->hasPermissionTo('kenaikan-kelas.kelola'))->toBeTrue();
});

it('seeds operator_akademik with the correct 54 academic-management permissions', function () {
    (new RoleSeeder)->run();

    $operatorAkademik = Role::where('name', 'operator_akademik')->first();
    expect($operatorAkademik)->not->toBeNull();
    expect($operatorAkademik->scope_level)->toBe('lembaga');
    expect($operatorAkademik->is_protected)->toBeFalse();
    expect($operatorAkademik->permissions()->count())->toBe(54);
    expect($operatorAkademik->hasPermissionTo('kelas.edit'))->toBeTrue();
    expect($operatorAkademik->hasPermissionTo('kurikulum-assignment.view'))->toBeTrue();
    expect($operatorAkademik->hasPermissionTo('siswa.import'))->toBeTrue();
    expect($operatorAkademik->hasPermissionTo('jadwal-pelajaran.kelola'))->toBeTrue();
    expect($operatorAkademik->hasPermissionTo('komponen-penilaian.kelola'))->toBeTrue();
    expect($operatorAkademik->hasPermissionTo('rapor.view'))->toBeTrue();
    expect($operatorAkademik->hasPermissionTo('rapor.verify'))->toBeTrue();
    expect($operatorAkademik->hasPermissionTo('kenaikan-kelas.kelola'))->toBeTrue();
    expect($operatorAkademik->hasPermissionTo('tahun-ajaran.view'))->toBeTrue();
    expect($operatorAkademik->hasPermissionTo('tahun-ajaran.create'))->toBeTrue();
    expect($operatorAkademik->hasPermissionTo('tahun-ajaran.activate'))->toBeTrue();
    expect($operatorAkademik->hasPermissionTo('semester.create'))->toBeTrue();
    expect($operatorAkademik->hasPermissionTo('semester.activate'))->toBeTrue();
    expect($operatorAkademik->hasPermissionTo('rpp.view'))->toBeTrue();
    expect($operatorAkademik->hasPermissionTo('rpp.kelola'))->toBeTrue();
    expect($operatorAkademik->hasPermissionTo('rpp.verify'))->toBeTrue();
});

it('gives guru EXACTLY the self-service baseline permission set', function () {
    (new RoleSeeder)->run();

    $guru = Role::where('name', 'guru')->firstOrFail();
    expect($guru->permissions()->pluck('name')->sort()->values()->all())->toBe([
        'asesmen.kelola',
        'kasus.ajukan',
        'kasus.view',
        'kehadiran-sdm.izin.ajukan',
        'kehadiran-sdm.izin.lihat-sendiri',
        'kehadiran-sdm.lihat-qr-sendiri',
        'komponen-penilaian.kelola-sendiri',
        'presensi.isi',
        'rapor.ajukan',
        'rapor.input-wali',
        'rpp.kelola',
        'rpp.view',
    ]);
});

it('gives pegawai_lembaga EXACTLY the self-service baseline permission set (no kasus.view)', function () {
    (new RoleSeeder)->run();

    $role = Role::where('name', 'pegawai_lembaga')->firstOrFail();
    expect($role->permissions()->pluck('name')->sort()->values()->all())->toBe([
        'kehadiran-sdm.izin.ajukan',
        'kehadiran-sdm.izin.lihat-sendiri',
        'kehadiran-sdm.lihat-qr-sendiri',
    ]);
});

it('gives pegawai_yayasan EXACTLY the self-service baseline permission set (no kasus.view)', function () {
    (new RoleSeeder)->run();

    $role = Role::where('name', 'pegawai_yayasan')->firstOrFail();
    expect($role->permissions()->pluck('name')->sort()->values()->all())->toBe([
        'kehadiran-sdm.izin.ajukan',
        'kehadiran-sdm.izin.lihat-sendiri',
        'kehadiran-sdm.lihat-qr-sendiri',
    ]);
});

it('gives siswa EXACTLY the self-service baseline permission set', function () {
    (new RoleSeeder)->run();

    $siswa = Role::where('name', 'siswa')->firstOrFail();
    expect($siswa->permissions()->pluck('name')->sort()->values()->all())->toBe([
        'kasus.view',
    ]);
});

it('gives orang_tua EXACTLY the self-service baseline permission set', function () {
    (new RoleSeeder)->run();

    $orangTua = Role::where('name', 'orang_tua')->firstOrFail();
    expect($orangTua->permissions()->pluck('name')->sort()->values()->all())->toBe([
        'kasus.ajukan',
        'kasus.consent',
        'kasus.view',
        'keuangan.akses',
    ]);
});

