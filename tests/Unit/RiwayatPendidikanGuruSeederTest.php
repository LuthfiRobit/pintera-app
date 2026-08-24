<?php
// tests/Unit/RiwayatPendidikanGuruSeederTest.php

use App\Models\Guru;
use App\Models\RiwayatPendidikanGuru;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\GuruSeeder;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RiwayatPendidikanGuruSeeder;
use Database\Seeders\RoleSeeder;
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

it('seeds education history for a guru with a known S1 record', function () {
    (new RiwayatPendidikanGuruSeeder())->run();

    $user = User::where('email', 'sari.wulandari@demo.test')->first();
    $guru = Guru::where('user_id', $user->id)->first();

    $riwayat = RiwayatPendidikanGuru::where('guru_id', $guru->id)->where('jenjang_pendidikan', 'S1')->first();
    expect($riwayat)->not->toBeNull();
    expect($riwayat->sekolah_formal)->toBe('Universitas Pendidikan Indonesia');
    expect($riwayat->bidang_studi)->toBe('Pendidikan Guru Sekolah Dasar');
});

it('seeds a guru with two education records (S1 and S2)', function () {
    (new RiwayatPendidikanGuruSeeder())->run();

    $user = User::where('email', 'hendra.gunawan@demo.test')->first();
    $guru = Guru::where('user_id', $user->id)->first();

    expect(RiwayatPendidikanGuru::where('guru_id', $guru->id)->count())->toBe(2);
});

it('is idempotent when run twice', function () {
    (new RiwayatPendidikanGuruSeeder())->run();
    (new RiwayatPendidikanGuruSeeder())->run();

    expect(RiwayatPendidikanGuru::count())->toBe(7);
});
