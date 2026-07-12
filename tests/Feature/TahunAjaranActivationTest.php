<?php

use App\Models\Lembaga;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;

it('deactivates the previously active tahun ajaran for the same lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_administrasi');
    $this->actingAs($user);

    $lama = TahunAjaran::create([
        'lembaga_id' => $lembaga->id,
        'nama' => '2025/2026',
        'tanggal_mulai' => '2025-07-01',
        'tanggal_selesai' => '2026-06-30',
        'status_aktif' => true,
    ]);

    $baru = TahunAjaran::create([
        'lembaga_id' => $lembaga->id,
        'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01',
        'tanggal_selesai' => '2027-06-30',
        'status_aktif' => false,
    ]);

    $baru->activate();

    expect($lama->fresh()->status_aktif)->toBeFalse();
    expect($baru->fresh()->status_aktif)->toBeTrue();
});

it('does not affect tahun ajaran belonging to a different lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $user = User::factory()->create();
    $user->assignRole('yayasan_super_admin');
    $this->actingAs($user);

    $tahunB = TahunAjaran::create([
        'lembaga_id' => $lembagaB->id,
        'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01',
        'tanggal_selesai' => '2027-06-30',
        'status_aktif' => true,
    ]);

    $tahunA = TahunAjaran::create([
        'lembaga_id' => $lembagaA->id,
        'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01',
        'tanggal_selesai' => '2027-06-30',
        'status_aktif' => false,
    ]);

    $tahunA->activate();

    expect($tahunB->fresh()->status_aktif)->toBeTrue();
});
