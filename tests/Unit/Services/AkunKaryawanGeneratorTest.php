<?php

use App\Models\JenisKaryawanMaster;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Yayasan;
use App\Services\AkunKaryawanGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('creates a dedicated karyawan and assigns the karyawan_lembaga role', function () {
    Role::firstOrCreate(['name' => 'karyawan_lembaga', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $jenis = JenisKaryawanMaster::factory()->create();

    $karyawan = (new AkunKaryawanGenerator())->buat(
        'Konselor Dedicated', '3201234567891111', $yayasan->id, $lembaga->id, $jenis->id, '081200001111', 'konselor@example.test',
    );

    $user = $karyawan->user;
    expect($user->username)->toBe('3201234567891111');
    expect($user->lembaga_id)->toBe($lembaga->id);
    expect(Hash::check('3201234567891111', $user->password))->toBeTrue();
    expect($user->must_change_password)->toBeTrue();
    expect($user->hasRole('karyawan_lembaga'))->toBeTrue();

    expect($karyawan->lembaga_id)->toBe($lembaga->id);
    expect($karyawan->yayasan_id)->toBe($yayasan->id);
    expect($karyawan->no_hp)->toBe('081200001111');
});

it('creates a pool karyawan (lembaga_id null) and assigns the karyawan_pool role', function () {
    Role::firstOrCreate(['name' => 'karyawan_pool', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $yayasan = Yayasan::factory()->create();
    $jenis = JenisKaryawanMaster::factory()->create();

    $karyawan = (new AkunKaryawanGenerator())->buat(
        'Psikolog Pool', '3201234567892222', $yayasan->id, null, $jenis->id,
    );

    expect($karyawan->user->lembaga_id)->toBeNull();
    expect($karyawan->user->hasRole('karyawan_pool'))->toBeTrue();
    expect($karyawan->lembaga_id)->toBeNull();
});
