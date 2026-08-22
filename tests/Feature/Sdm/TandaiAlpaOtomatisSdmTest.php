<?php
// tests/Feature/Sdm/TandaiAlpaOtomatisSdmTest.php

use App\Domains\Sdm\Enums\AttendanceStatus;
use App\Domains\Sdm\Models\AttendanceRecord;
use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\Yayasan;
use Carbon\Carbon;

it('marks an active guru with no attendance record as Alpa for a work-day yesterday', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-25 01:00:00')); // Tuesday
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => 'aktif']);

    $this->artisan('sdm:tandai-alpa-otomatis')->assertSuccessful();

    $record = AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->first();
    expect($record)->not->toBeNull();
    expect($record->status)->toBe(AttendanceStatus::Alpa);
    expect($record->tanggal->toDateString())->toBe('2026-08-24'); // Monday, H-1

    Carbon::setTestNow();
});

it('does not mark anyone Alpa for a lembaga whose yesterday was a libur day', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-24 01:00:00')); // Monday, so H-1 = Sunday
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => 'aktif']);

    $this->artisan('sdm:tandai-alpa-otomatis')->assertSuccessful();

    expect(AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->exists())->toBeFalse();

    Carbon::setTestNow();
});

it('does not mark a karyawan with status_aktif non_aktif', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-25 01:00:00')); // Tuesday
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    $karyawan = Karyawan::factory()->create(['lembaga_id' => $lembaga->id, 'yayasan_id' => $yayasan->id, 'status_aktif' => 'non_aktif']);

    $this->artisan('sdm:tandai-alpa-otomatis')->assertSuccessful();

    expect(AttendanceRecord::where('pegawai_type', Karyawan::class)->where('pegawai_id', $karyawan->id)->exists())->toBeFalse();

    Carbon::setTestNow();
});

it('is idempotent when run twice for the same day', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-25 01:00:00')); // Tuesday
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => 'aktif']);

    $this->artisan('sdm:tandai-alpa-otomatis')->assertSuccessful();
    $this->artisan('sdm:tandai-alpa-otomatis')->assertSuccessful();

    expect(AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->count())->toBe(1);

    Carbon::setTestNow();
});

it('skips a guru who already has a manual attendance record for that day', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-25 01:00:00')); // Tuesday
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => 'aktif']);
    $admin = \App\Models\User::factory()->create(['lembaga_id' => $lembaga->id]);

    app(\App\Domains\Sdm\Actions\RecordManualAttendanceAction::class)->execute($guru, new \App\Domains\Sdm\DataTransferObjects\RecordManualAttendanceData(
        lembagaId: $lembaga->id, arah: 'masuk', status: AttendanceStatus::Hadir,
        waktu: \Carbon\CarbonImmutable::parse('2026-08-24 07:00:00'), dicatatOlehUserId: $admin->id,
    ));

    $this->artisan('sdm:tandai-alpa-otomatis')->assertSuccessful();

    $record = AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->first();
    expect($record->status)->toBe(AttendanceStatus::Hadir);

    Carbon::setTestNow();
});
