<?php
// tests/Unit/DatabaseSeederTest.php

use App\Models\Role;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('makes the new academic-module permissions exist and reach yayasan_super_admin after a fresh full seed', function () {
    $this->seed(DatabaseSeeder::class);

    $newPermissions = [
        'mata-pelajaran.view', 'mata-pelajaran.create', 'mata-pelajaran.edit',
        'kelas.view', 'kelas.create', 'kelas.edit',
        'siswa.view', 'siswa.create', 'siswa.edit',
    ];

    foreach ($newPermissions as $name) {
        expect(Permission::where('name', $name)->exists())
            ->toBeTrue("Expected permission [{$name}] to have been auto-discovered by permissions:sync during the seed chain.");
    }

    $superAdmin = Role::where('name', 'yayasan_super_admin')->firstOrFail();

    foreach ($newPermissions as $name) {
        expect($superAdmin->hasPermissionTo($name))
            ->toBeTrue("Expected yayasan_super_admin to have been granted [{$name}].");
    }
});

it('is idempotent when the full DatabaseSeeder is run twice for the permission sync step', function () {
    $this->seed(DatabaseSeeder::class);
    $countFirstRun = Permission::count();

    $this->seed(DatabaseSeeder::class);

    expect(Permission::count())->toBe($countFirstRun);

    $superAdmin = Role::where('name', 'yayasan_super_admin')->firstOrFail();
    expect($superAdmin->hasPermissionTo('siswa.view'))->toBeTrue();
});
