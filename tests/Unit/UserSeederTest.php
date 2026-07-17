<?php

use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new PermissionSeeder())->run();
    (new RoleSeeder())->run();
    Yayasan::factory()->create(['nama' => 'Yayasan Pendidikan Islam Al-Hikmah']);
    (new LembagaSeeder())->run();
});

it('seeds the yayasan admin and per-lembaga staff with correct roles and lembaga_id', function () {
    (new UserSeeder())->run();

    $adminYayasan = User::where('email', 'admin.yayasan@alhikmah.sch.id')->first();
    expect($adminYayasan)->not->toBeNull();
    expect($adminYayasan->hasRole('yayasan_super_admin'))->toBeTrue();
    expect($adminYayasan->lembaga_id)->toBeNull();
    expect($adminYayasan->email_verified_at)->not->toBeNull();

    $smp = Lembaga::where('npsn', '20223344')->first();
    $kepsekSmp = User::where('email', 'kepsek.smp@alhikmah.sch.id')->first();
    expect($kepsekSmp->hasRole('kepala_sekolah'))->toBeTrue();
    expect($kepsekSmp->lembaga_id)->toBe($smp->id);

    $keuanganSmp = User::where('email', 'keuangan.smp@alhikmah.sch.id')->first();
    expect($keuanganSmp->hasRole('admin_keuangan'))->toBeTrue();

    $guruSmp = User::where('email', 'budi.santoso@alhikmah.sch.id')->first();
    expect($guruSmp->hasRole('guru'))->toBeTrue();
    expect($guruSmp->lembaga_id)->toBe($smp->id);

    $sma = Lembaga::where('npsn', '20223355')->first();
    $kepsekSma = User::where('email', 'kepsek.sma@alhikmah.sch.id')->first();
    expect($kepsekSma->hasRole('kepala_sekolah'))->toBeTrue();
    expect($kepsekSma->lembaga_id)->toBe($sma->id);
});

it('is idempotent when run twice', function () {
    (new UserSeeder())->run();
    (new UserSeeder())->run();

    expect(User::count())->toBe(13);
});
