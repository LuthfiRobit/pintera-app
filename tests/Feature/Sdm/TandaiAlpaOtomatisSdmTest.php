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

it('marks a karyawan with a policy hari_kerja override as Alpa even on a lembaga-libur day', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-24 01:00:00')); // Monday, so H-1 = Sunday (lembaga libur)
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    $jenisKaryawan = \App\Models\JenisKaryawanMaster::factory()->create();
    $karyawan = Karyawan::factory()->create(['lembaga_id' => $lembaga->id, 'yayasan_id' => $yayasan->id, 'jenis_karyawan_id' => $jenisKaryawan->id, 'status_aktif' => 'aktif']);
    \App\Domains\Sdm\Models\AttendancePolicy::create([
        'yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jenis_karyawan_id' => $jenisKaryawan->id,
        'jam_masuk' => '18:00', 'toleransi_menit' => 10, 'hari_kerja' => [0, 1, 2, 3, 4, 5, 6],
    ]);

    $this->artisan('sdm:tandai-alpa-otomatis')->assertSuccessful();

    $record = AttendanceRecord::where('pegawai_type', Karyawan::class)->where('pegawai_id', $karyawan->id)->first();
    expect($record)->not->toBeNull();
    expect($record->status)->toBe(AttendanceStatus::Alpa);

    Carbon::setTestNow();
});

it('still skips a guru with no policy override on a lembaga-libur day, alongside a policy-overridden karyawan in the same lembaga', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-24 01:00:00')); // Monday, H-1 = Sunday (lembaga libur)
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_kelas', 'status_aktif' => 'aktif']);
    $jenisKaryawan = \App\Models\JenisKaryawanMaster::factory()->create();
    $karyawan = Karyawan::factory()->create(['lembaga_id' => $lembaga->id, 'yayasan_id' => $yayasan->id, 'jenis_karyawan_id' => $jenisKaryawan->id, 'status_aktif' => 'aktif']);
    \App\Domains\Sdm\Models\AttendancePolicy::create([
        'yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jenis_karyawan_id' => $jenisKaryawan->id,
        'jam_masuk' => '18:00', 'toleransi_menit' => 10, 'hari_kerja' => [0, 1, 2, 3, 4, 5, 6],
    ]);

    $this->artisan('sdm:tandai-alpa-otomatis')->assertSuccessful();

    expect(AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->exists())->toBeFalse();
    expect(AttendanceRecord::where('pegawai_type', Karyawan::class)->where('pegawai_id', $karyawan->id)->exists())->toBeTrue();

    Carbon::setTestNow();
});

it('does NOT mark a karyawan as Alpa on a lembaga work day when the policy hari_kerja override excludes that day (reverse direction of the shift gap)', function () {
    // Regression guard for the OTHER direction of the celah: a part-time-style category
    // (Policy hari_kerja narrower than the lembaga's default work week) must NOT be wrongly
    // marked Alpa on a day the lembaga calendar says is a work day but the pegawai's own
    // Policy says is not.
    Carbon::setTestNow(Carbon::parse('2026-08-21 01:00:00')); // Friday, so H-1 = Thursday (lembaga work day)
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]); // Mon-Sat is lembaga work days
    $jenisKaryawan = \App\Models\JenisKaryawanMaster::factory()->create();
    $karyawan = Karyawan::factory()->create(['lembaga_id' => $lembaga->id, 'yayasan_id' => $yayasan->id, 'jenis_karyawan_id' => $jenisKaryawan->id, 'status_aktif' => 'aktif']);
    \App\Domains\Sdm\Models\AttendancePolicy::create([
        'yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jenis_karyawan_id' => $jenisKaryawan->id,
        'jam_masuk' => '08:00', 'toleransi_menit' => 10, 'hari_kerja' => [1, 2, 3], // Only Mon-Wed for this category
    ]);

    $this->artisan('sdm:tandai-alpa-otomatis')->assertSuccessful();

    expect(AttendanceRecord::where('pegawai_type', Karyawan::class)->where('pegawai_id', $karyawan->id)->exists())->toBeFalse();

    Carbon::setTestNow();
});

