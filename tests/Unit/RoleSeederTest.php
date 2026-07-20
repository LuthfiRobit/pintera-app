<?php

use App\Models\Role;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new PermissionSeeder())->run();
});

it('seeds 5 roles with correct scope and protection', function () {
    (new RoleSeeder())->run();

    $superAdmin = Role::where('name', 'yayasan_super_admin')->first();
    expect($superAdmin->scope_level)->toBe('yayasan');
    expect($superAdmin->is_protected)->toBeTrue();
    expect($superAdmin->permissions()->count())->toBe(52);

    expect(Role::where('name', 'kepala_sekolah')->first()->scope_level)->toBe('lembaga');
    expect(Role::where('name', 'admin_administrasi')->first()->scope_level)->toBe('lembaga');
    expect(Role::where('name', 'admin_keuangan')->first()->scope_level)->toBe('lembaga');
    expect(Role::where('name', 'guru')->first()->scope_level)->toBe('diri_sendiri');
});

it('gives admin_administrasi the correct 20 SPMB-related permissions', function () {
    (new RoleSeeder())->run();

    $adminAdministrasi = Role::where('name', 'admin_administrasi')->first();
    expect($adminAdministrasi->permissions()->count())->toBe(20);
    expect($adminAdministrasi->hasPermissionTo('jalur-ppdb.create'))->toBeTrue();
});

it('gives kepala_sekolah the correct 6 permissions', function () {
    (new RoleSeeder())->run();

    $kepalaSekolah = Role::where('name', 'kepala_sekolah')->first();
    expect($kepalaSekolah->permissions()->count())->toBe(6);
    expect($kepalaSekolah->hasPermissionTo('spmb-pendaftaran.tetapkan-keputusan'))->toBeTrue();
});

it('gives admin_keuangan the correct 11 permissions', function () {
    (new RoleSeeder())->run();

    $adminKeuangan = Role::where('name', 'admin_keuangan')->first();
    expect($adminKeuangan->permissions()->count())->toBe(11);
    expect($adminKeuangan->hasPermissionTo('cicilan.kelola'))->toBeTrue();
});

it('is idempotent when run twice', function () {
    (new RoleSeeder())->run();
    (new RoleSeeder())->run();

    expect(Role::count())->toBe(5);
});
