<?php

use Spatie\Permission\Models\Permission;

it('creates a permission for every $this->authorize() call found in app/Http/Controllers', function () {
    $this->artisan('permissions:sync')->assertExitCode(0);

    expect(Permission::where('name', 'guru.view')->exists())->toBeTrue();
    expect(Permission::where('name', 'tahun-ajaran.activate')->exists())->toBeTrue();
});

it('reports permissions that exist in the database but are no longer referenced by any controller, without deleting them', function () {
    Permission::firstOrCreate(['name' => 'modul-lama.aksi-usang', 'guard_name' => 'web']);

    $this->artisan('permissions:sync')
        ->expectsOutputToContain('modul-lama.aksi-usang')
        ->assertExitCode(0);

    expect(Permission::where('name', 'modul-lama.aksi-usang')->exists())->toBeTrue();
});

it('is safe to run twice in a row without creating duplicates', function () {
    $this->artisan('permissions:sync')->assertExitCode(0);
    $countAfterFirstRun = Permission::where('name', 'guru.view')->count();

    $this->artisan('permissions:sync')->assertExitCode(0);
    $countAfterSecondRun = Permission::where('name', 'guru.view')->count();

    expect($countAfterFirstRun)->toBe(1);
    expect($countAfterSecondRun)->toBe(1);
});
