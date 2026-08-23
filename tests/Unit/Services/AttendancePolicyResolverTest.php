<?php
// tests/Unit/Services/AttendancePolicyResolverTest.php

use App\Domains\Sdm\Models\AttendancePolicy;
use App\Domains\Sdm\Services\AttendancePolicyResolver;
use App\Models\Guru;
use App\Domains\Sdm\Models\JenisKaryawanMaster;
use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\Yayasan;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('resolves the lembaga-specific policy over the yayasan default for the same jenis_ptk', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_kelas']);

    AttendancePolicy::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => null, 'jenis_ptk' => 'guru_kelas', 'jam_masuk' => '07:00', 'toleransi_menit' => 0]);
    AttendancePolicy::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_kelas', 'jam_masuk' => '07:30', 'toleransi_menit' => 10]);

    $policy = app(AttendancePolicyResolver::class)->resolvePolicy($guru);

    expect($policy->jam_masuk)->toBe('07:30:00');
    expect($policy->toleransi_menit)->toBe(10);
});

it('falls back to the yayasan default policy when no lembaga override exists', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_mapel']);

    AttendancePolicy::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => null, 'jenis_ptk' => 'guru_mapel', 'jam_masuk' => '07:00', 'toleransi_menit' => 5]);

    $policy = app(AttendancePolicyResolver::class)->resolvePolicy($guru);

    expect($policy->jam_masuk)->toBe('07:00:00');
});

it('returns null when no policy exists for the pegawai category at any scope', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_bk']);

    $policy = app(AttendancePolicyResolver::class)->resolvePolicy($guru);

    expect($policy)->toBeNull();
});

it('resolves a karyawan policy by jenis_karyawan_id independently from jenis_ptk policies', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $jenisKaryawan = JenisKaryawanMaster::factory()->create();
    $karyawan = Karyawan::factory()->create(['lembaga_id' => $lembaga->id, 'yayasan_id' => $yayasan->id, 'jenis_karyawan_id' => $jenisKaryawan->id]);

    AttendancePolicy::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jenis_karyawan_id' => $jenisKaryawan->id, 'jam_masuk' => '18:00', 'toleransi_menit' => 10]);

    $policy = app(AttendancePolicyResolver::class)->resolvePolicy($karyawan);

    expect($policy->jam_masuk)->toBe('18:00:00');
});

it('resolveLibur overrides the calendar with the policy hari_kerja when set', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-23')); // Sunday
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    $jenisKaryawan = JenisKaryawanMaster::factory()->create();
    $karyawan = Karyawan::factory()->create(['lembaga_id' => $lembaga->id, 'yayasan_id' => $yayasan->id, 'jenis_karyawan_id' => $jenisKaryawan->id]);

    AttendancePolicy::create([
        'yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jenis_karyawan_id' => $jenisKaryawan->id,
        'jam_masuk' => '18:00', 'toleransi_menit' => 10, 'hari_kerja' => [0, 1, 2, 3, 4, 5, 6],
    ]);

    $result = app(AttendancePolicyResolver::class)->resolveLibur($karyawan, Carbon::now());

    expect($result)->toBe(['libur' => false, 'alasan' => 'Hari kerja sesuai kebijakan peran']);
    Carbon::setTestNow();
});

it('resolveLibur delegates entirely to the calendar resolver when the policy has no hari_kerja override', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_kelas']);
    AttendancePolicy::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_kelas', 'jam_masuk' => '07:00', 'toleransi_menit' => 0]);

    $result = app(AttendancePolicyResolver::class)->resolveLibur($guru, \Carbon\Carbon::parse('2026-08-23')); // Sunday

    expect($result['libur'])->toBeTrue();
    expect($result['alasan'])->toBe('Libur mingguan SDM');
});

it('resolveLibur delegates entirely to the calendar resolver when the pegawai has no policy at all', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_bk']);

    $result = app(AttendancePolicyResolver::class)->resolveLibur($guru, \Carbon\Carbon::parse('2026-08-19')); // Wednesday

    expect($result)->toBe(['libur' => false, 'alasan' => 'Hari kerja efektif']);
});
