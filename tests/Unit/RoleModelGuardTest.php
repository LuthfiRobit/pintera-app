<?php

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('throws when saving a name change on a protected role', function () {
    $role = Role::create(['name' => 'guru', 'guard_name' => 'web', 'scope_level' => 'diri_sendiri', 'is_protected' => true]);

    $role->name = 'Guru Pengajar';

    expect(fn () => $role->save())->toThrow(RuntimeException::class, 'Nama role yang dilindungi tidak dapat diubah.');

    expect($role->fresh()->name)->toBe('guru');
});

it('still throws when saving a scope_level change on a protected role (regression, existing guard)', function () {
    $role = Role::create(['name' => 'yayasan_super_admin', 'guard_name' => 'web', 'scope_level' => 'yayasan', 'is_protected' => true]);

    $role->scope_level = 'lembaga';

    expect(fn () => $role->save())->toThrow(RuntimeException::class);
});

it('allows changing permissions (via syncPermissions) on a protected role without touching name/scope_level', function () {
    $role = Role::create(['name' => 'guru', 'guard_name' => 'web', 'scope_level' => 'diri_sendiri', 'is_protected' => true]);
    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'kasus.view', 'guard_name' => 'web']);

    $role->syncPermissions(['kasus.view']);

    expect($role->fresh()->hasPermissionTo('kasus.view'))->toBeTrue();
    expect($role->fresh()->name)->toBe('guru');
});

it('allows changing name freely on a non-protected role', function () {
    $role = Role::create(['name' => 'admin_perpustakaan', 'guard_name' => 'web', 'scope_level' => 'lembaga', 'is_protected' => false]);

    $role->name = 'admin_perpustakaan_v2';
    $role->save();

    expect($role->fresh()->name)->toBe('admin_perpustakaan_v2');
});
