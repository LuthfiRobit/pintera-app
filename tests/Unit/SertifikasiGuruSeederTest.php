<?php
// tests/Unit/SertifikasiGuruSeederTest.php

use App\Models\Guru;
use App\Models\SertifikasiGuru;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\GuruSeeder;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SertifikasiGuruSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new PermissionSeeder())->run();
    (new RoleSeeder())->run();
    Yayasan::factory()->create();
    (new LembagaSeeder())->run();
    (new UserSeeder())->run();
    (new GuruSeeder())->run();
});

it('seeds certification only for guru who have one, leaving others without', function () {
    (new SertifikasiGuruSeeder())->run();

    $bersertifikat = User::where('email', 'sari.wulandari@demo.test')->first();
    $guruBersertifikat = Guru::where('user_id', $bersertifikat->id)->first();
    expect(SertifikasiGuru::where('guru_id', $guruBersertifikat->id)->exists())->toBeTrue();

    $tanpaSertifikat = User::where('email', 'agus.setiawan@demo.test')->first();
    $guruTanpaSertifikat = Guru::where('user_id', $tanpaSertifikat->id)->first();
    expect(SertifikasiGuru::where('guru_id', $guruTanpaSertifikat->id)->exists())->toBeFalse();
});

it('is idempotent when run twice', function () {
    (new SertifikasiGuruSeeder())->run();
    (new SertifikasiGuruSeeder())->run();

    expect(SertifikasiGuru::count())->toBe(3);
});
