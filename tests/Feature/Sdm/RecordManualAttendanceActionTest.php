<?php
// tests/Feature/Sdm/RecordManualAttendanceActionTest.php

use App\Domains\Sdm\Actions\RecordManualAttendanceAction;
use App\Domains\Sdm\DataTransferObjects\RecordManualAttendanceData;
use App\Domains\Sdm\Enums\AttendanceStatus;
use App\Domains\Sdm\Models\AttendanceRecord;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Carbon\CarbonImmutable;

it('records a manual check-in event and creates an aggregate record for the day', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $waktu = CarbonImmutable::parse('2026-08-22 07:15:00');

    $action = app(RecordManualAttendanceAction::class);
    $event = $action->execute($guru, new RecordManualAttendanceData(
        lembagaId: $lembaga->id,
        arah: 'masuk',
        status: AttendanceStatus::Hadir,
        waktu: $waktu,
        dicatatOlehUserId: $admin->id,
    ));

    expect($event->arah)->toBe('masuk');
    expect($event->status)->toBe(AttendanceStatus::Hadir);

    $record = AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->first();
    expect($record)->not->toBeNull();
    expect($record->status)->toBe(AttendanceStatus::Hadir);
    expect($record->waktu_masuk->format('H:i'))->toBe('07:15');
    expect($record->waktu_pulang)->toBeNull();
});

it('merges a check-out event into the same day record produced by an earlier check-in', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $action = app(RecordManualAttendanceAction::class);

    $action->execute($guru, new RecordManualAttendanceData(
        lembagaId: $lembaga->id, arah: 'masuk', status: AttendanceStatus::Hadir,
        waktu: CarbonImmutable::parse('2026-08-22 07:00:00'), dicatatOlehUserId: $admin->id,
    ));
    $action->execute($guru, new RecordManualAttendanceData(
        lembagaId: $lembaga->id, arah: 'pulang', status: AttendanceStatus::Hadir,
        waktu: CarbonImmutable::parse('2026-08-22 15:30:00'), dicatatOlehUserId: $admin->id,
    ));

    expect(AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->count())->toBe(1);
    $record = AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->first();
    expect($record->waktu_masuk->format('H:i'))->toBe('07:00');
    expect($record->waktu_pulang->format('H:i'))->toBe('15:30');
});

it('overrides the day status to izin when an izin event exists alongside a hadir event', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $action = app(RecordManualAttendanceAction::class);

    $action->execute($guru, new RecordManualAttendanceData(
        lembagaId: $lembaga->id, arah: 'masuk', status: AttendanceStatus::Hadir,
        waktu: CarbonImmutable::parse('2026-08-22 07:00:00'), dicatatOlehUserId: $admin->id,
    ));
    $action->execute($guru, new RecordManualAttendanceData(
        lembagaId: $lembaga->id, arah: 'pulang', status: AttendanceStatus::Izin,
        waktu: CarbonImmutable::parse('2026-08-22 09:00:00'), dicatatOlehUserId: $admin->id,
        catatan: 'Izin keperluan keluarga setelah absen masuk.',
    ));

    $record = AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->first();
    expect($record->status)->toBe(AttendanceStatus::Izin);
});

it('rejects a manual attendance record on a day the calendar resolver marks as libur', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $action = app(\App\Domains\Sdm\Actions\RecordManualAttendanceAction::class);

    expect(fn () => $action->execute($guru, new RecordManualAttendanceData(
        lembagaId: $lembaga->id, arah: 'masuk', status: AttendanceStatus::Hadir,
        waktu: CarbonImmutable::parse('2026-08-23 07:00:00'), dicatatOlehUserId: $admin->id, // Sunday
    )))->toThrow(\App\Domains\Sdm\Exceptions\AttendanceOnHolidayException::class);

    expect(AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->exists())->toBeFalse();
});

it('allows a manual attendance record on a libur day when overrideHariLibur is true', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $action = app(\App\Domains\Sdm\Actions\RecordManualAttendanceAction::class);

    $event = $action->execute($guru, new RecordManualAttendanceData(
        lembagaId: $lembaga->id, arah: 'masuk', status: AttendanceStatus::Hadir,
        waktu: CarbonImmutable::parse('2026-08-23 07:00:00'), dicatatOlehUserId: $admin->id, // Sunday
        overrideHariLibur: true,
    ));

    expect($event->arah)->toBe('masuk');
    expect(AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->exists())->toBeTrue();
});

it('allows a manual attendance record on a lembaga-libur day when the pegawai category has a policy hari_kerja override', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    $jenisKaryawan = \App\Models\JenisKaryawanMaster::factory()->create();
    $karyawan = \App\Models\Karyawan::factory()->create(['lembaga_id' => $lembaga->id, 'yayasan_id' => $yayasan->id, 'jenis_karyawan_id' => $jenisKaryawan->id]);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    \App\Domains\Sdm\Models\AttendancePolicy::create([
        'yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jenis_karyawan_id' => $jenisKaryawan->id,
        'jam_masuk' => '18:00', 'toleransi_menit' => 10, 'hari_kerja' => [0, 1, 2, 3, 4, 5, 6],
    ]);
    $action = app(\App\Domains\Sdm\Actions\RecordManualAttendanceAction::class);

    $event = $action->execute($karyawan, new RecordManualAttendanceData(
        lembagaId: $lembaga->id, arah: 'masuk', status: AttendanceStatus::Hadir,
        waktu: CarbonImmutable::parse('2026-08-23 18:05:00'), dicatatOlehUserId: $admin->id, // Sunday, but policy overrides it as a work day
    ));

    expect($event->arah)->toBe('masuk');
    expect(AttendanceRecord::where('pegawai_type', \App\Models\Karyawan::class)->where('pegawai_id', $karyawan->id)->exists())->toBeTrue();
});

