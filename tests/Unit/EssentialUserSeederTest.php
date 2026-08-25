<?php
// tests/Unit/EssentialUserSeederTest.php

use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\EssentialUserSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new PermissionSeeder())->run();
    (new RoleSeeder())->run();
});

it('creates only the superadmin account when no lembaga exists yet', function () {
    (new EssentialUserSeeder())->run();

    $superAdmin = User::where('email', 'superadmin@demo.test')->first();
    expect($superAdmin)->not->toBeNull();
    expect($superAdmin->hasRole('yayasan_super_admin'))->toBeTrue();
    expect($superAdmin->lembaga_id)->toBeNull();

    expect(User::where('email', 'kepsek.sd@demo.test')->exists())->toBeFalse();
});

it('creates all 7 essential accounts when a lembaga exists, attaching the lembaga-scoped ones to it', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    (new EssentialUserSeeder())->run();

    expect(User::where('email', 'superadmin@demo.test')->exists())->toBeTrue();

    $kepsek = User::where('email', 'kepsek.sd@demo.test')->first();
    expect($kepsek->hasRole('kepala_sekolah'))->toBeTrue();
    expect($kepsek->lembaga_id)->toBe($lembaga->id);

    $adm = User::where('email', 'adm.sd@demo.test')->first();
    expect($adm->hasRole('admin_administrasi'))->toBeTrue();

    $keuangan = User::where('email', 'keuangan.sd@demo.test')->first();
    expect($keuangan->hasRole('bendahara_lembaga'))->toBeTrue();

    $guru = User::where('email', 'guru.sd1@demo.test')->first();
    expect($guru->hasRole('guru'))->toBeTrue();

    $akademik = User::where('email', 'kurikulum.sd@demo.test')->first();
    expect($akademik->hasRole('operator_akademik'))->toBeTrue();
    expect($akademik->lembaga_id)->toBe($lembaga->id);

    $sarpras = User::where('email', 'sarpras.sd@demo.test')->first();
    expect($sarpras->hasRole('admin_sarpras'))->toBeTrue();
    expect($sarpras->lembaga_id)->toBe($lembaga->id);
});

it('is idempotent when run twice', function () {
    Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);

    (new EssentialUserSeeder())->run();
    (new EssentialUserSeeder())->run();

    expect(User::count())->toBe(7);
});
