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
    (new PermissionSeeder())->run();
});

it('seeds 6 roles with correct scope and protection', function () {
    (new RoleSeeder())->run();

    $superAdmin = Role::where('name', 'yayasan_super_admin')->first();
    expect($superAdmin->scope_level)->toBe('yayasan');
    expect($superAdmin->is_protected)->toBeTrue();
    expect($superAdmin->permissions()->count())->toBe(138);

    expect(Role::where('name', 'kepala_sekolah')->first()->scope_level)->toBe('lembaga');
    expect(Role::where('name', 'admin_administrasi')->first()->scope_level)->toBe('lembaga');
    expect(Role::where('name', 'admin_keuangan')->first()->scope_level)->toBe('lembaga');
    expect(Role::where('name', 'guru')->first()->scope_level)->toBe('diri_sendiri');
    expect(Role::where('name', 'admin_akademik')->first()->scope_level)->toBe('lembaga');
    expect(Role::where('name', 'admin_sdm')->first()->scope_level)->toBe('lembaga');
});

it('gives admin_administrasi the correct 20 SPMB-related permissions', function () {
    (new RoleSeeder())->run();

    $adminAdministrasi = Role::where('name', 'admin_administrasi')->first();
    expect($adminAdministrasi->permissions()->count())->toBe(20);
    expect($adminAdministrasi->hasPermissionTo('jalur-ppdb.create'))->toBeTrue();
});

it('gives kepala_sekolah the correct 12 permissions', function () {
    (new RoleSeeder())->run();

    $kepalaSekolah = Role::where('name', 'kepala_sekolah')->first();
    expect($kepalaSekolah->permissions()->count())->toBe(12);
    expect($kepalaSekolah->hasPermissionTo('spmb-pendaftaran.tetapkan-keputusan'))->toBeTrue();
    expect($kepalaSekolah->hasPermissionTo('komponen-penilaian.kelola'))->toBeTrue();
    expect($kepalaSekolah->hasPermissionTo('rapor.view'))->toBeTrue();
    expect($kepalaSekolah->hasPermissionTo('rapor.approve'))->toBeTrue();
    expect($kepalaSekolah->hasPermissionTo('kenaikan-kelas.kelola'))->toBeTrue();
    expect($kepalaSekolah->hasPermissionTo('rpp.view'))->toBeTrue();
    expect($kepalaSekolah->hasPermissionTo('rpp.verify'))->toBeTrue();
});

it('gives admin_keuangan the correct 12 permissions', function () {
    (new RoleSeeder())->run();

    $adminKeuangan = Role::where('name', 'admin_keuangan')->first();
    expect($adminKeuangan->permissions()->count())->toBe(12);
    expect($adminKeuangan->hasPermissionTo('cicilan.kelola'))->toBeTrue();
    expect($adminKeuangan->hasPermissionTo('pembayaran.virtual-account'))->toBeTrue();
});

it('gives guru the presensi, asesmen, and komponen-penilaian-sendiri permissions', function () {
    (new RoleSeeder())->run();

    $guru = Role::where('name', 'guru')->first();
    expect($guru->permissions()->count())->toBe(10);
    expect($guru->hasPermissionTo('presensi.isi'))->toBeTrue();
    expect($guru->hasPermissionTo('asesmen.kelola'))->toBeTrue();
    expect($guru->hasPermissionTo('komponen-penilaian.kelola-sendiri'))->toBeTrue();
    expect($guru->hasPermissionTo('rapor.input-wali'))->toBeTrue();
    expect($guru->hasPermissionTo('rapor.ajukan'))->toBeTrue();
    expect($guru->hasPermissionTo('rpp.view'))->toBeTrue();
    expect($guru->hasPermissionTo('rpp.kelola'))->toBeTrue();
    expect($guru->hasPermissionTo('kehadiran-sdm.lihat-qr-sendiri'))->toBeTrue();
});

it('is idempotent when run twice', function () {
    (new RoleSeeder())->run();
    (new RoleSeeder())->run();

    expect(Role::count())->toBe(13);
});

it('grants kalender-akademik.kelola-nasional to yayasan_super_admin via bulk permission sync, but not to admin_akademik', function () {
    (new RoleSeeder())->run();

    expect(Permission::where('name', 'kalender-akademik.kelola-nasional')->exists())->toBeTrue();

    $superAdmin = Role::where('name', 'yayasan_super_admin')->firstOrFail();
    expect($superAdmin->hasPermissionTo('kalender-akademik.kelola-nasional'))->toBeTrue();

    $adminAkademik = Role::where('name', 'admin_akademik')->firstOrFail();
    expect($adminAkademik->hasPermissionTo('kalender-akademik.kelola-nasional'))->toBeFalse();
});

it('also gets the full permission set re-synced by RolePermissionAssignmentSeeder at the end of the full seed chain', function () {
    (new RoleSeeder())->run();
    (new RolePermissionAssignmentSeeder())->run();

    $superAdmin = Role::where('name', 'yayasan_super_admin')->firstOrFail();
    expect($superAdmin->permissions()->count())->toBe(Permission::count());
});

it('grants kenaikan-kelas.kelola to kepala_sekolah after permissions sync and role seeding', function () {
    Artisan::call('permissions:sync');
    (new RoleSeeder())->run();

    $kepalaSekolah = Role::where('name', 'kepala_sekolah')->firstOrFail();

    expect($kepalaSekolah->hasPermissionTo('kenaikan-kelas.kelola'))->toBeTrue();
});

it('seeds admin_akademik with the correct 30 academic-management permissions', function () {
    (new RoleSeeder())->run();

    $adminAkademik = Role::where('name', 'admin_akademik')->first();
    expect($adminAkademik)->not->toBeNull();
    expect($adminAkademik->scope_level)->toBe('lembaga');
    expect($adminAkademik->is_protected)->toBeFalse();
    expect($adminAkademik->permissions()->count())->toBe(46);
    expect($adminAkademik->hasPermissionTo('kelas.edit'))->toBeTrue();
    expect($adminAkademik->hasPermissionTo('siswa.import'))->toBeTrue();
    expect($adminAkademik->hasPermissionTo('jadwal-pelajaran.kelola'))->toBeTrue();
    expect($adminAkademik->hasPermissionTo('komponen-penilaian.kelola'))->toBeTrue();
    expect($adminAkademik->hasPermissionTo('rapor.view'))->toBeTrue();
    expect($adminAkademik->hasPermissionTo('rapor.verify'))->toBeTrue();
    expect($adminAkademik->hasPermissionTo('kenaikan-kelas.kelola'))->toBeTrue();
    expect($adminAkademik->hasPermissionTo('tahun-ajaran.view'))->toBeTrue();
    expect($adminAkademik->hasPermissionTo('tahun-ajaran.create'))->toBeTrue();
    expect($adminAkademik->hasPermissionTo('tahun-ajaran.activate'))->toBeTrue();
    expect($adminAkademik->hasPermissionTo('semester.create'))->toBeTrue();
    expect($adminAkademik->hasPermissionTo('semester.activate'))->toBeTrue();
    expect($adminAkademik->hasPermissionTo('rpp.view'))->toBeTrue();
    expect($adminAkademik->hasPermissionTo('rpp.kelola'))->toBeTrue();
    expect($adminAkademik->hasPermissionTo('rpp.verify'))->toBeTrue();
});
