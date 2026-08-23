<?php
// tests/Feature/Sdm/AttendancePolicyModelTest.php

use App\Domains\Sdm\Models\AttendancePolicy;
use App\Domains\Sdm\Models\JenisKaryawanMaster;
use App\Models\Lembaga;
use App\Models\Yayasan;

it('creates an attendance policy for a guru category (jenis_ptk)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $policy = AttendancePolicy::create([
        'yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id,
        'jenis_ptk' => 'guru_kelas', 'jenis_karyawan_id' => null,
        'jam_masuk' => '07:00', 'toleransi_menit' => 15,
    ]);

    expect($policy->jenis_ptk)->toBe('guru_kelas');
    expect($policy->jenis_karyawan_id)->toBeNull();
    expect($policy->toleransi_menit)->toBe(15);
});

it('creates an attendance policy for a karyawan category (jenis_karyawan_id) with a hari_kerja override', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $jenisKaryawan = JenisKaryawanMaster::factory()->create(['nama' => 'Satpam']);

    $policy = AttendancePolicy::create([
        'yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id,
        'jenis_ptk' => null, 'jenis_karyawan_id' => $jenisKaryawan->id,
        'jam_masuk' => '18:00', 'jam_pulang' => '06:00', 'toleransi_menit' => 10,
        'hari_kerja' => [0, 1, 2, 3, 4, 5, 6],
    ]);

    expect($policy->jenis_karyawan_id)->toBe($jenisKaryawan->id);
    expect($policy->hari_kerja)->toBe([0, 1, 2, 3, 4, 5, 6]);
});
