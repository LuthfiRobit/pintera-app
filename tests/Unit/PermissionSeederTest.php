<?php
// tests/Unit/PermissionSeederTest.php

use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('seeds exactly 109 permissions', function () {
    (new PermissionSeeder())->run();

    expect(Permission::count())->toBe(109);
    expect(Permission::where('name', 'roles.view')->exists())->toBeTrue();
    expect(Permission::where('name', 'cicilan.kelola')->exists())->toBeTrue();
    expect(Permission::where('name', 'pembayaran.virtual-account')->exists())->toBeTrue();
});

it('seeds the kalender-akademik.kelola-nasional permission row', function () {
    (new PermissionSeeder())->run();

    expect(Permission::where('name', 'kalender-akademik.kelola-nasional')->exists())->toBeTrue();
});

it('includes the yayasan.kelola permission', function () {
    (new \Database\Seeders\PermissionSeeder())->run();

    expect(\Spatie\Permission\Models\Permission::where('name', 'yayasan.kelola')->exists())->toBeTrue();
});

it('is idempotent when run twice', function () {
    (new PermissionSeeder())->run();
    (new PermissionSeeder())->run();

    expect(Permission::count())->toBe(109);
});

it('removes orphaned legacy flat-name permissions on re-seed', function () {
    Permission::firstOrCreate(['name' => 'manage-guru', 'guard_name' => 'web']);

    (new PermissionSeeder())->run();

    expect(Permission::where('name', 'manage-guru')->exists())->toBeFalse();
});
