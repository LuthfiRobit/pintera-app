<?php
// tests/Feature/Sdm/AttendanceRecordAggregatorLateDetectionTest.php

use App\Domains\Sdm\Actions\RecordManualAttendanceAction;
use App\Domains\Sdm\DataTransferObjects\RecordManualAttendanceData;
use App\Domains\Sdm\Enums\AttendanceStatus;
use App\Domains\Sdm\Models\AttendancePolicy;
use App\Domains\Sdm\Models\AttendanceRecord;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Carbon\CarbonImmutable;

it('marks is_late true with correct late_minutes when arriving after jam_masuk plus toleransi', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_kelas']);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    AttendancePolicy::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_kelas', 'jam_masuk' => '07:00', 'toleransi_menit' => 15]);

    app(RecordManualAttendanceAction::class)->execute($guru, new RecordManualAttendanceData(
        lembagaId: $lembaga->id, arah: 'masuk', status: AttendanceStatus::Hadir,
        waktu: CarbonImmutable::parse('2026-08-24 07:20:00'), dicatatOlehUserId: $admin->id, // Monday
    ));

    $record = AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->first();
    expect($record->is_late)->toBeTrue();
    expect($record->late_minutes)->toBe(5);
});

it('marks is_late false when arriving within the toleransi window', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_kelas']);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    AttendancePolicy::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_kelas', 'jam_masuk' => '07:00', 'toleransi_menit' => 15]);

    app(RecordManualAttendanceAction::class)->execute($guru, new RecordManualAttendanceData(
        lembagaId: $lembaga->id, arah: 'masuk', status: AttendanceStatus::Hadir,
        waktu: CarbonImmutable::parse('2026-08-24 07:10:00'), dicatatOlehUserId: $admin->id, // Monday
    ));

    $record = AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->first();
    expect($record->is_late)->toBeFalse();
    expect($record->late_minutes)->toBe(0);
});

it('leaves is_late false and late_minutes null when the pegawai has no policy at all', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_bk']);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);

    app(RecordManualAttendanceAction::class)->execute($guru, new RecordManualAttendanceData(
        lembagaId: $lembaga->id, arah: 'masuk', status: AttendanceStatus::Hadir,
        waktu: CarbonImmutable::parse('2026-08-24 10:00:00'), dicatatOlehUserId: $admin->id, // Monday, clearly "late" by any normal standard
    ));

    $record = AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->first();
    expect($record->is_late)->toBeFalse();
    expect($record->late_minutes)->toBeNull();
});

it('marks is_late true with toleransi 0 when a shift-assigned pegawai with no policy arrives after the shift jam_masuk', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_bk']);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisShift = \App\Domains\Sdm\Models\JenisShift::create([
        'yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'nama' => 'Shift Pagi',
        'jam_masuk' => '06:00', 'jam_pulang' => '14:00',
    ]);
    app(\App\Domains\Sdm\Actions\AssignShiftAction::class)->execute($guru, new \App\Domains\Sdm\DataTransferObjects\ShiftAssignmentData(
        lembagaId: $lembaga->id, jenisShiftId: $jenisShift->id, tanggalMulai: '2026-08-24', tanggalSelesai: '2026-08-24',
    ));

    app(RecordManualAttendanceAction::class)->execute($guru, new RecordManualAttendanceData(
        lembagaId: $lembaga->id, arah: 'masuk', status: AttendanceStatus::Hadir,
        waktu: CarbonImmutable::parse('2026-08-24 06:10:00'), dicatatOlehUserId: $admin->id, // Monday
    ));

    $record = AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->first();
    expect($record->is_late)->toBeTrue();
    expect($record->late_minutes)->toBe(10);
});

