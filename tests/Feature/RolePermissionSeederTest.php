<?php

use App\Models\Role;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Permission;

it('seeds the initial permissions', function () {
    (new RolePermissionSeeder())->run();

    $expected = [
        'manage-roles', 'manage-users', 'manage-yayasan',
        'manage-lembaga', 'manage-tahun-ajaran', 'manage-guru', 'view-audit-log',
    ];

    foreach ($expected as $name) {
        expect(Permission::where('name', $name)->exists())->toBeTrue();
    }
});

it('seeds the initial roles with correct scope and protection', function () {
    (new RolePermissionSeeder())->run();

    $superAdmin = Role::where('name', 'yayasan_super_admin')->first();
    expect($superAdmin->scope_level)->toBe('yayasan');
    expect($superAdmin->is_protected)->toBeTrue();
    expect($superAdmin->permissions()->count())->toBe(7);

    expect(Role::where('name', 'kepala_sekolah')->first()->scope_level)->toBe('lembaga');
    expect(Role::where('name', 'admin_administrasi')->first()->scope_level)->toBe('lembaga');
    expect(Role::where('name', 'admin_keuangan')->first()->scope_level)->toBe('lembaga');
    expect(Role::where('name', 'guru')->first()->scope_level)->toBe('diri_sendiri');
});

it('is idempotent when run twice', function () {
    (new RolePermissionSeeder())->run();
    (new RolePermissionSeeder())->run();

    expect(Role::count())->toBe(5);
    expect(Permission::count())->toBe(7);
});
