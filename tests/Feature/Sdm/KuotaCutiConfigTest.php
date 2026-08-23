<?php
// tests/Feature/Sdm/KuotaCutiConfigTest.php

use App\Domains\Sdm\Models\KuotaCutiConfig;
use App\Domains\Sdm\Models\JenisKaryawanMaster;
use App\Models\Lembaga;
use App\Models\Yayasan;

it('creates a flat kuota cuti config row for a lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $config = KuotaCutiConfig::create([
        'yayasan_id' => $yayasan->id,
        'lembaga_id' => $lembaga->id,
        'jenis_ptk' => null,
        'jenis_karyawan_id' => null,
        'jatah_hari_per_tahun' => 12,
    ]);

    expect($config->jatah_hari_per_tahun)->toBe(12);
    expect(KuotaCutiConfig::find($config->id)->lembaga_id)->toBe($lembaga->id);
});

it('rejects a duplicate config row for the exact same scope', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $jenisKaryawan = JenisKaryawanMaster::factory()->create();

    KuotaCutiConfig::create([
        'yayasan_id' => $yayasan->id,
        'lembaga_id' => $lembaga->id,
        'jenis_ptk' => 'guru_kelas',
        'jenis_karyawan_id' => $jenisKaryawan->id,
        'jatah_hari_per_tahun' => 12,
    ]);

    expect(fn () => KuotaCutiConfig::create([
        'yayasan_id' => $yayasan->id,
        'lembaga_id' => $lembaga->id,
        'jenis_ptk' => 'guru_kelas',
        'jenis_karyawan_id' => $jenisKaryawan->id,
        'jatah_hari_per_tahun' => 15,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
