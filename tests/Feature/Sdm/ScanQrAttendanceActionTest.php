<?php
// tests/Feature/Sdm/ScanQrAttendanceActionTest.php

use App\Domains\Sdm\Actions\GenerateEmployeeQrTokenAction;
use App\Domains\Sdm\Actions\ScanQrAttendanceAction;
use App\Domains\Sdm\DataTransferObjects\ScanQrAttendanceData;
use App\Domains\Sdm\Enums\AttendanceMethod;
use App\Domains\Sdm\Exceptions\InvalidQrTokenException;
use App\Domains\Sdm\Exceptions\QrTokenLembagaMismatchException;
use App\Domains\Sdm\Models\AttendanceRecord;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;

it('records a hadir event when scanning a valid token for a pegawai in the same lembaga as the petugas', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $petugas = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $qr = app(GenerateEmployeeQrTokenAction::class)->execute($guru);

    $event = app(ScanQrAttendanceAction::class)->execute(new ScanQrAttendanceData(
        token: $qr->token, arah: 'masuk', lembagaId: $lembaga->id, dicatatOlehUserId: $petugas->id,
    ));

    expect($event->method)->toBe(AttendanceMethod::Qr);
    expect(AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->exists())->toBeTrue();
});

it('rejects an unknown or inactive token', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $petugas = User::factory()->create(['lembaga_id' => $lembaga->id]);

    expect(fn () => app(ScanQrAttendanceAction::class)->execute(new ScanQrAttendanceData(
        token: 'token-tidak-pernah-ada', arah: 'masuk', lembagaId: $lembaga->id, dicatatOlehUserId: $petugas->id,
    )))->toThrow(InvalidQrTokenException::class);
});

it('rejects a token belonging to an employee from a different lembaga than the scanning petugas', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guruLembagaB = Guru::factory()->create(['lembaga_id' => $lembagaB->id]);
    $petugasLembagaA = User::factory()->create(['lembaga_id' => $lembagaA->id]);
    $qr = app(GenerateEmployeeQrTokenAction::class)->execute($guruLembagaB);

    expect(fn () => app(ScanQrAttendanceAction::class)->execute(new ScanQrAttendanceData(
        token: $qr->token, arah: 'masuk', lembagaId: $lembagaA->id, dicatatOlehUserId: $petugasLembagaA->id,
    )))->toThrow(QrTokenLembagaMismatchException::class);

    expect(AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guruLembagaB->id)->exists())->toBeFalse();
});

it('rejects a qr scan on a day the calendar resolver marks as libur, with no override path', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => range(0, 6)]); // every day is libur
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $petugas = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $qr = app(\App\Domains\Sdm\Actions\GenerateEmployeeQrTokenAction::class)->execute($guru);

    expect(fn () => app(ScanQrAttendanceAction::class)->execute(new ScanQrAttendanceData(
        token: $qr->token, arah: 'masuk', lembagaId: $lembaga->id, dicatatOlehUserId: $petugas->id,
    )))->toThrow(\App\Domains\Sdm\Exceptions\AttendanceOnHolidayException::class);

    expect(AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->exists())->toBeFalse();
});
