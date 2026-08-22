<?php
// tests/Feature/Sdm/AttendanceRbacSeedTest.php

use App\Models\Role;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;

it('seeds the admin_sdm role with all 4 kehadiran-sdm permissions', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RoleSeeder']);

    foreach (['kehadiran-sdm.view', 'kehadiran-sdm.catat', 'kehadiran-sdm.kelola-konfigurasi', 'kehadiran-sdm.lihat-qr-sendiri'] as $permission) {
        expect(Permission::where('name', $permission)->exists())->toBeTrue();
    }

    $role = Role::where('name', 'admin_sdm')->first();
    expect($role)->not->toBeNull();
    expect($role->scope_level)->toBe('lembaga');
    expect($role->hasPermissionTo('kehadiran-sdm.view'))->toBeTrue();
    expect($role->hasPermissionTo('kehadiran-sdm.catat'))->toBeTrue();
    expect($role->hasPermissionTo('kehadiran-sdm.kelola-konfigurasi'))->toBeTrue();
    expect($role->hasPermissionTo('kehadiran-sdm.lihat-qr-sendiri'))->toBeTrue();
});

it('gives guru and karyawan roles the lihat-qr-sendiri permission only', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RoleSeeder']);

    foreach (['guru', 'karyawan_pool', 'karyawan_lembaga'] as $roleName) {
        $role = Role::where('name', $roleName)->first();
        expect($role->hasPermissionTo('kehadiran-sdm.lihat-qr-sendiri'))->toBeTrue();
        expect($role->hasPermissionTo('kehadiran-sdm.kelola-konfigurasi'))->toBeFalse();
    }
});
