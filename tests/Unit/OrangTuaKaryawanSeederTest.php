<?php

use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\JenisKaryawanMasterSeeder;
use Database\Seeders\OrangTuaKaryawanSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new PermissionSeeder())->run();
    (new RoleSeeder())->run();
    (new JenisKaryawanMasterSeeder())->run();
});

it('creates a Satpam and a Petugas Kebersihan karyawan via the real AkunKaryawanGenerator flow, lembaga-scoped', function () {
    // Menegaskan staf umum non-PTK (satpam, cleaning service) mendapat profil
    // Karyawan yang benar lewat jalur resmi (AkunKaryawanGenerator, sama dengan
    // KaryawanController::store()) -- BUKAN lewat form Pengguna generik, yang
    // tidak pernah membuat profil kepegawaian (lihat PETA_PENGEMBANGAN.md).
    $yayasan = Yayasan::factory()->create();
    Lembaga::factory()->create(['npsn' => '20223333', 'yayasan_id' => $yayasan->id]);

    (new OrangTuaKaryawanSeeder())->run();

    $satpam = User::where('username', '3273019900020001')->first();
    expect($satpam)->not->toBeNull();
    expect($satpam->hasRole('pegawai_lembaga'))->toBeTrue();
    expect($satpam->lembaga_id)->not->toBeNull();

    $karyawanSatpam = Karyawan::where('user_id', $satpam->id)->first();
    expect($karyawanSatpam)->not->toBeNull();
    expect($karyawanSatpam->nama)->toBe('Slamet Riyadi');
    expect($karyawanSatpam->jenisKaryawan->nama)->toBe('Satpam');

    $cleaning = User::where('username', '3273019900020002')->first();
    expect($cleaning)->not->toBeNull();
    $karyawanCleaning = Karyawan::where('user_id', $cleaning->id)->first();
    expect($karyawanCleaning->jenisKaryawan->nama)->toBe('Petugas Kebersihan');
});

it('is idempotent when run twice', function () {
    $yayasan = Yayasan::factory()->create();
    Lembaga::factory()->create(['npsn' => '20223333', 'yayasan_id' => $yayasan->id]);

    (new OrangTuaKaryawanSeeder())->run();
    (new OrangTuaKaryawanSeeder())->run();

    expect(User::where('username', '3273019900020001')->count())->toBe(1);
    expect(User::where('username', '3273019900020002')->count())->toBe(1);
});
