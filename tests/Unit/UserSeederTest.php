<?php

use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Database\Seeders\YayasanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new PermissionSeeder())->run();
    (new RoleSeeder())->run();
    (new YayasanSeeder())->run();
    (new LembagaSeeder())->run();
});

it('seeds the yayasan admin and per-lembaga staff for the SD institution', function () {
    (new UserSeeder())->run();

    $adminYayasan = User::where('email', 'adm.yayasan@demo.test')->first();
    expect($adminYayasan)->not->toBeNull();
    expect($adminYayasan->hasRole('yayasan_super_admin'))->toBeTrue();
    expect($adminYayasan->lembaga_id)->toBeNull();
    expect($adminYayasan->email_verified_at)->not->toBeNull();

    $sdit = Lembaga::where('npsn', '20223333')->first();
    $kepsekSd = User::where('email', 'kepsek.sd@demo.test')->first();
    expect($kepsekSd->hasRole('kepala_sekolah'))->toBeTrue();
    expect($kepsekSd->lembaga_id)->toBe($sdit->id);

    $admSd = User::where('email', 'adm.sd@demo.test')->first();
    expect($admSd->hasRole('admin_administrasi'))->toBeTrue();
    expect($admSd->lembaga_id)->toBe($sdit->id);

    $keuanganSd = User::where('email', 'keuangan.sd@demo.test')->first();
    expect($keuanganSd->hasRole('admin_keuangan'))->toBeTrue();
    expect($keuanganSd->lembaga_id)->toBe($sdit->id);

    $guruSd = User::where('email', 'hendra.gunawan@demo.test')->first();
    expect($guruSd->hasRole('guru'))->toBeTrue();
    expect($guruSd->lembaga_id)->toBe($sdit->id);
});

it('is idempotent when run twice', function () {
    (new UserSeeder())->run();
    (new UserSeeder())->run();

    expect(User::count())->toBe(19);
});
