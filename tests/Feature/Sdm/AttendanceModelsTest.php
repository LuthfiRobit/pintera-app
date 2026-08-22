<?php
// tests/Feature/Sdm/AttendanceModelsTest.php

use App\Domains\Sdm\Enums\AttendanceMethod;
use App\Domains\Sdm\Enums\AttendanceStatus;
use App\Domains\Sdm\Models\AttendanceEvent;
use App\Domains\Sdm\Models\AttendanceMethodConfiguration;
use App\Domains\Sdm\Models\AttendancePoint;
use App\Domains\Sdm\Models\AttendanceRecord;
use App\Domains\Sdm\Models\EmployeeQrCode;
use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;

it('creates an attendance method configuration scoped to a yayasan default row', function () {
    $yayasan = Yayasan::factory()->create();

    $config = AttendanceMethodConfiguration::create([
        'yayasan_id' => $yayasan->id,
        'lembaga_id' => null,
        'method' => AttendanceMethod::Admin,
        'is_enabled' => true,
    ]);

    expect($config->method)->toBe(AttendanceMethod::Admin);
    expect($config->is_enabled)->toBeTrue();
});

it('creates an attendance point for a lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $point = AttendancePoint::create(['lembaga_id' => $lembaga->id, 'nama' => 'Gerbang Utama']);

    expect($point->nama)->toBe('Gerbang Utama');
    expect($point->is_active)->toBeTrue();
});

it('creates an attendance event for a guru via the morph relation and reads it back through Guru::attendanceEvents()', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    $event = $guru->attendanceEvents()->create([
        'lembaga_id' => $lembaga->id,
        'method' => AttendanceMethod::Admin,
        'arah' => 'masuk',
        'status' => AttendanceStatus::Hadir,
        'waktu' => now(),
    ]);

    expect($event->pegawai_type)->toBe(Guru::class);
    expect($event->pegawai_id)->toBe($guru->id);
    expect($guru->attendanceEvents()->count())->toBe(1);
    expect($event->pegawai->id)->toBe($guru->id);
});

it('creates an attendance event for a karyawan via the morph relation', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $karyawan = Karyawan::factory()->create(['lembaga_id' => $lembaga->id, 'yayasan_id' => $yayasan->id]);

    $event = $karyawan->attendanceEvents()->create([
        'lembaga_id' => $lembaga->id,
        'method' => AttendanceMethod::Qr,
        'arah' => 'masuk',
        'status' => AttendanceStatus::Hadir,
        'waktu' => now(),
    ]);

    expect($event->pegawai_type)->toBe(Karyawan::class);
    expect($karyawan->attendanceEvents()->count())->toBe(1);
});

it('upserts an attendance record keyed by pegawai and tanggal', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $tanggal = now()->toDateString();

    $record = AttendanceRecord::updateOrCreate(
        ['pegawai_type' => Guru::class, 'pegawai_id' => $guru->id, 'tanggal' => $tanggal],
        ['lembaga_id' => $lembaga->id, 'status' => AttendanceStatus::Hadir, 'waktu_masuk' => now()]
    );

    expect(AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->count())->toBe(1);
    expect($record->status)->toBe(AttendanceStatus::Hadir);

    AttendanceRecord::updateOrCreate(
        ['pegawai_type' => Guru::class, 'pegawai_id' => $guru->id, 'tanggal' => $tanggal],
        ['lembaga_id' => $lembaga->id, 'status' => AttendanceStatus::Izin]
    );

    expect(AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->count())->toBe(1);
    expect($record->fresh()->status)->toBe(AttendanceStatus::Izin);
});

it('creates and reads an employee qr code via Guru::employeeQrCode()', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    EmployeeQrCode::create(['pegawai_type' => Guru::class, 'pegawai_id' => $guru->id, 'token' => str()->random(48), 'is_active' => true]);

    expect($guru->fresh()->employeeQrCode)->not->toBeNull();
    expect($guru->fresh()->employeeQrCode->is_active)->toBeTrue();
});
