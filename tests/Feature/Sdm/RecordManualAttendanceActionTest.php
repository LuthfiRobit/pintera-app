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
