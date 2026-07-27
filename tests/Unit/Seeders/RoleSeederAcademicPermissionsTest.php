<?php

use App\Models\Role;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('grants presensi and asesmen permissions to the guru role', function () {
    Artisan::call('permissions:sync');
    (new RoleSeeder())->run();

    $guru = Role::where('name', 'guru')->firstOrFail();

    expect($guru->hasPermissionTo('presensi.isi'))->toBeTrue();
    expect($guru->hasPermissionTo('asesmen.kelola'))->toBeTrue();
});

it('grants komponen-penilaian and rapor permissions to kepala_sekolah', function () {
    Artisan::call('permissions:sync');
    (new RoleSeeder())->run();

    $kepsek = Role::where('name', 'kepala_sekolah')->firstOrFail();

    expect($kepsek->hasPermissionTo('komponen-penilaian.kelola'))->toBeTrue();
    expect($kepsek->hasPermissionTo('rapor.view'))->toBeTrue();
});
