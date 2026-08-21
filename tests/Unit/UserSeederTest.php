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

it('seeds the yayasan admin and per-lembaga staff across all 4 K-9 institutions', function () {
    (new UserSeeder())->run();

    $adminYayasan = User::where('email', 'adm.yayasan@demo.test')->first();
    expect($adminYayasan)->not->toBeNull();
    expect($adminYayasan->hasRole('yayasan_super_admin'))->toBeTrue();
    expect($adminYayasan->lembaga_id)->toBeNull();
    expect($adminYayasan->email_verified_at)->not->toBeNull();

    $smpit = Lembaga::where('npsn', '20223344')->first();
    $kepsekSmp = User::where('email', 'kepsek.smp@demo.test')->first();
    expect($kepsekSmp->hasRole('kepala_sekolah'))->toBeTrue();
    expect($kepsekSmp->lembaga_id)->toBe($smpit->id);

    $sdit = Lembaga::where('npsn', '20223333')->first();
    $kepsekSd = User::where('email', 'kepsek.sd@demo.test')->first();
    expect($kepsekSd->hasRole('kepala_sekolah'))->toBeTrue();
    expect($kepsekSd->lembaga_id)->toBe($sdit->id);

    $tkit = Lembaga::where('npsn', '20223322')->first();
    $admTk = User::where('email', 'adm.tk@demo.test')->first();
    expect($admTk->hasRole('admin_administrasi'))->toBeTrue();
    expect($admTk->lembaga_id)->toBe($tkit->id);

    $kbit = Lembaga::where('npsn', '20223311')->first();
    $keuanganKb = User::where('email', 'keuangan.kb@demo.test')->first();
    expect($keuanganKb->hasRole('admin_keuangan'))->toBeTrue();
    expect($keuanganKb->lembaga_id)->toBe($kbit->id);

    $guruSmp = User::where('email', 'budi.santoso@demo.test')->first();
    expect($guruSmp->hasRole('guru'))->toBeTrue();
    expect($guruSmp->lembaga_id)->toBe($smpit->id);

    $guruSd = User::where('email', 'hendra.gunawan@demo.test')->first();
    expect($guruSd->hasRole('guru'))->toBeTrue();
    expect($guruSd->lembaga_id)->toBe($sdit->id);
});

it('is idempotent when run twice', function () {
    (new UserSeeder())->run();
    (new UserSeeder())->run();

    expect(User::count())->toBe(25);
});
