<?php

use App\Domains\Sdm\Models\JenisKaryawanMaster;
use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;

it('creates a dedicated-lembaga karyawan with all relations resolvable', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $jenis = JenisKaryawanMaster::factory()->create(['nama' => 'Konselor BK']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $karyawan = Karyawan::factory()->create([
        'user_id' => $user->id,
        'yayasan_id' => $yayasan->id,
        'lembaga_id' => $lembaga->id,
        'jenis_karyawan_id' => $jenis->id,
        'nama' => 'Budi Konselor',
        'nik' => '3201234567891234',
        'no_hp' => '081234567890',
        'status_aktif' => 'aktif',
    ]);

    expect($karyawan->user->id)->toBe($user->id);
    expect($karyawan->yayasan->id)->toBe($yayasan->id);
    expect($karyawan->lembaga->id)->toBe($lembaga->id);
    expect($karyawan->jenisKaryawan->nama)->toBe('Konselor BK');
});

it('creates a pool karyawan with lembaga_id null', function () {
    $yayasan = Yayasan::factory()->create();
    $jenis = JenisKaryawanMaster::factory()->create(['nama' => 'Psikolog']);
    $user = User::factory()->create(['lembaga_id' => null]);

    $karyawan = Karyawan::factory()->create([
        'user_id' => $user->id,
        'yayasan_id' => $yayasan->id,
        'lembaga_id' => null,
        'jenis_karyawan_id' => $jenis->id,
        'nama' => 'Siti Psikolog',
        'nik' => '3201234567895678',
        'no_hp' => '081298765432',
        'status_aktif' => 'aktif',
    ]);

    expect($karyawan->lembaga_id)->toBeNull();
    expect($karyawan->yayasan_id)->toBe($yayasan->id);
});
