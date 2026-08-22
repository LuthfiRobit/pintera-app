<?php
// tests/Unit/Services/ShiftAwareAttendanceResolverTest.php

use App\Domains\Sdm\Actions\AssignShiftAction;
use App\Domains\Sdm\DataTransferObjects\ShiftAssignmentData;
use App\Domains\Sdm\Models\AttendancePolicy;
use App\Domains\Sdm\Models\JenisShift;
use App\Domains\Sdm\Services\ShiftAwareAttendanceResolver;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Yayasan;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('resolveLibur delegates fully to AttendancePolicyResolver when no shift assignment is active', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_bk']);

    $result = app(ShiftAwareAttendanceResolver::class)->resolveLibur($guru, Carbon::parse('2026-08-19')); // Wednesday

    expect($result)->toBe(['libur' => false, 'alasan' => 'Hari kerja efektif']);
});

it('resolveLibur treats every day in range as a work day when the shift has no hari_kerja override', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisShift = JenisShift::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'nama' => 'Shift Malam', 'jam_masuk' => '22:00', 'jam_pulang' => '06:00']);
    app(AssignShiftAction::class)->execute($guru, new ShiftAssignmentData(lembagaId: $lembaga->id, jenisShiftId: $jenisShift->id, tanggalMulai: '2026-08-17', tanggalSelesai: '2026-08-23'));

    $result = app(ShiftAwareAttendanceResolver::class)->resolveLibur($guru, Carbon::parse('2026-08-23')); // Sunday, but shift active

    expect($result)->toBe(['libur' => false, 'alasan' => 'Hari kerja sesuai jadwal shift Shift Malam']);
});

it('resolveLibur respects the shift hari_kerja override when set', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisShift = JenisShift::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'nama' => 'Shift Malam', 'jam_masuk' => '22:00', 'jam_pulang' => '06:00']);
    app(AssignShiftAction::class)->execute($guru, new ShiftAssignmentData(lembagaId: $lembaga->id, jenisShiftId: $jenisShift->id, tanggalMulai: '2026-08-17', tanggalSelesai: '2026-08-23', hariKerja: [1, 2, 3]));

    $result = app(ShiftAwareAttendanceResolver::class)->resolveLibur($guru, Carbon::parse('2026-08-20')); // Thursday, not in [1,2,3]

    expect($result)->toBe(['libur' => true, 'alasan' => 'Libur sesuai jadwal shift Shift Malam']);
});

it('resolveJamKerjaEfektif uses the shift jam_masuk with toleransi 0 when the pegawai has no AttendancePolicy at all', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_bk']);
    $jenisShift = JenisShift::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'nama' => 'Shift Pagi', 'jam_masuk' => '06:00', 'jam_pulang' => '14:00']);
    app(AssignShiftAction::class)->execute($guru, new ShiftAssignmentData(lembagaId: $lembaga->id, jenisShiftId: $jenisShift->id, tanggalMulai: '2026-08-17', tanggalSelesai: '2026-08-23'));

    $result = app(ShiftAwareAttendanceResolver::class)->resolveJamKerjaEfektif($guru, Carbon::parse('2026-08-19'));

    expect($result)->toBe(['jam_masuk' => '06:00:00', 'toleransi_menit' => 0]);
});

it('resolveJamKerjaEfektif combines the shift jam_masuk with the pegawai AttendancePolicy toleransi when a Policy exists', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_kelas']);
    AttendancePolicy::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_kelas', 'jam_masuk' => '07:00', 'toleransi_menit' => 20]);
    $jenisShift = JenisShift::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'nama' => 'Shift Pagi', 'jam_masuk' => '06:00', 'jam_pulang' => '14:00']);
    app(AssignShiftAction::class)->execute($guru, new ShiftAssignmentData(lembagaId: $lembaga->id, jenisShiftId: $jenisShift->id, tanggalMulai: '2026-08-17', tanggalSelesai: '2026-08-23'));

    $result = app(ShiftAwareAttendanceResolver::class)->resolveJamKerjaEfektif($guru, Carbon::parse('2026-08-19'));

    expect($result)->toBe(['jam_masuk' => '06:00:00', 'toleransi_menit' => 20]);
});

it('resolveJamKerjaEfektif returns null when there is no shift and no policy (fail-safe unchanged)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_bk']);

    $result = app(ShiftAwareAttendanceResolver::class)->resolveJamKerjaEfektif($guru, Carbon::parse('2026-08-19'));

    expect($result)->toBeNull();
});
