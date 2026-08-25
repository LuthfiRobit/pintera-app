<?php
// tests/Feature/Rbac/RoleTaxonomyInvariantTest.php

use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use App\Services\AkunKaryawanGenerator;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new PermissionSeeder())->run();
    (new RoleSeeder())->run();
});

// ── M2: pegawai_lembaga XOR pegawai_yayasan via AkunKaryawanGenerator ──────

it('assigns pegawai_lembaga when AkunKaryawanGenerator creates a lembaga-scoped karyawan', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $jenisKaryawan = \App\Domains\Sdm\Models\JenisKaryawanMaster::factory()->create();

    $karyawan = app(AkunKaryawanGenerator::class)->buat(
        'Test Karyawan', '1234567890123456', $yayasan->id, $lembaga->id, $jenisKaryawan->id
    );

    expect($karyawan->user->hasRole('pegawai_lembaga'))->toBeTrue();
    expect($karyawan->user->hasRole('pegawai_yayasan'))->toBeFalse();
});

it('assigns pegawai_yayasan when AkunKaryawanGenerator creates a pool karyawan (null lembaga)', function () {
    $yayasan = Yayasan::factory()->create();
    $jenisKaryawan = \App\Domains\Sdm\Models\JenisKaryawanMaster::factory()->create();

    $karyawan = app(AkunKaryawanGenerator::class)->buat(
        'Test Karyawan Pool', '1234567890123457', $yayasan->id, null, $jenisKaryawan->id
    );

    expect($karyawan->user->hasRole('pegawai_yayasan'))->toBeTrue();
    expect($karyawan->user->hasRole('pegawai_lembaga'))->toBeFalse();
});

it('rejects a non-yayasan_super_admin from creating a pool karyawan via is_pool', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $jenisKaryawan = \App\Domains\Sdm\Models\JenisKaryawanMaster::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('operator_akademik');
    $admin->givePermissionTo('karyawan.create');

    $this->actingAs($admin)->post(route('admin.karyawan.store'), [
        'is_pool' => '1',
        'yayasan_id' => $yayasan->id,
        'nik' => '1234567890123458',
        'nama' => 'Test Pool Ditolak',
        'jenis_karyawan_id' => $jenisKaryawan->id,
    ])->assertForbidden();
});

// ── M3, M7: widestScopeLevel() regression untuk pegawai_* ──────────────────

it('resolves widestScopeLevel to lembaga for a user with only pegawai_lembaga', function () {
    $user = User::factory()->create();
    $user->assignRole('pegawai_lembaga');

    expect($user->widestScopeLevel())->toBe('lembaga');
});

it('resolves widestScopeLevel to yayasan for a user with only pegawai_yayasan', function () {
    $user = User::factory()->create();
    $user->assignRole('pegawai_yayasan');

    expect($user->widestScopeLevel())->toBe('yayasan');
});

// ── Multi-role composition ──────────────────────────────────────────────────

it('keeps all permissions active when a user has pegawai_lembaga + guru + wali_kelas combined', function () {
    $user = User::factory()->create();
    $user->assignRole(['pegawai_lembaga', 'guru', 'wali_kelas']);

    expect($user->hasRole('pegawai_lembaga'))->toBeTrue();
    expect($user->hasRole('guru'))->toBeTrue();
    expect($user->hasRole('wali_kelas'))->toBeTrue();
    expect($user->can('kasus.ajukan'))->toBeTrue(); // dari role guru
    expect($user->can('kehadiran-sdm.lihat-qr-sendiri'))->toBeTrue(); // dari role pegawai_lembaga
});

// ── Wali Kelas: capability vs relation (spec §8) ────────────────────────────

it('denies a wali_kelas-role guru from managing a kelas that is not theirs via wali_kelas_guru_id', function () {
    $lembaga = Lembaga::factory()->create();
    $guruLain = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'wali_kelas_guru_id' => $guruLain->id]);

    $userWaliKelas = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $userWaliKelas->assignRole(['pegawai_lembaga', 'guru', 'wali_kelas']);
    $guruSaya = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'user_id' => $userWaliKelas->id]);

    // Pola authorization yang benar (spec §8): role HANYA capability gate,
    // relasi Kelas.wali_kelas_guru_id yang menentukan kelas mana yang dikelola.
    $bolehKelola = $userWaliKelas->hasRole('wali_kelas') && $kelas->wali_kelas_guru_id === $guruSaya->id;

    expect($bolehKelola)->toBeFalse();
});

it('allows a wali_kelas-role guru to manage the kelas they are actually assigned to via wali_kelas_guru_id', function () {
    $lembaga = Lembaga::factory()->create();
    $userWaliKelas = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $userWaliKelas->assignRole(['pegawai_lembaga', 'guru', 'wali_kelas']);
    $guruSaya = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'user_id' => $userWaliKelas->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'wali_kelas_guru_id' => $guruSaya->id]);

    $bolehKelola = $userWaliKelas->hasRole('wali_kelas') && $kelas->wali_kelas_guru_id === $guruSaya->id;

    expect($bolehKelola)->toBeTrue();
});
