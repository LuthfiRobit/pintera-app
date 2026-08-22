<?php
// tests/Feature/Sdm/AssignShiftActionTest.php

use App\Domains\Sdm\Actions\AssignShiftAction;
use App\Domains\Sdm\DataTransferObjects\ShiftAssignmentData;
use App\Domains\Sdm\Models\JenisShift;
use App\Domains\Sdm\Models\PenugasanShift;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Yayasan;

it('creates a shift assignment for a guru with no existing overlap', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisShift = JenisShift::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'nama' => 'Shift Pagi', 'jam_masuk' => '06:00', 'jam_pulang' => '14:00']);

    $penugasan = app(AssignShiftAction::class)->execute($guru, new ShiftAssignmentData(
        lembagaId: $lembaga->id, jenisShiftId: $jenisShift->id, tanggalMulai: '2026-09-01', tanggalSelesai: '2026-09-07',
    ));

    expect($penugasan->pegawai_type)->toBe(Guru::class);
    expect($penugasan->pegawai_id)->toBe($guru->id);
});

it('rejects a new assignment overlapping an existing one for the same pegawai', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisShift = JenisShift::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'nama' => 'Shift Pagi', 'jam_masuk' => '06:00', 'jam_pulang' => '14:00']);
    $action = app(AssignShiftAction::class);
    $action->execute($guru, new ShiftAssignmentData(lembagaId: $lembaga->id, jenisShiftId: $jenisShift->id, tanggalMulai: '2026-09-01', tanggalSelesai: '2026-09-07'));

    expect(fn () => $action->execute($guru, new ShiftAssignmentData(
        lembagaId: $lembaga->id, jenisShiftId: $jenisShift->id, tanggalMulai: '2026-09-05', tanggalSelesai: '2026-09-10',
    )))->toThrow(\App\Domains\Sdm\Exceptions\ShiftAssignmentOverlapException::class);

    expect(PenugasanShift::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->count())->toBe(1);
});

it('rejects an overlap when the existing assignment has no end date (open-ended)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisShift = JenisShift::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'nama' => 'Shift Pagi', 'jam_masuk' => '06:00', 'jam_pulang' => '14:00']);
    $action = app(AssignShiftAction::class);
    $action->execute($guru, new ShiftAssignmentData(lembagaId: $lembaga->id, jenisShiftId: $jenisShift->id, tanggalMulai: '2026-09-01', tanggalSelesai: null));

    expect(fn () => $action->execute($guru, new ShiftAssignmentData(
        lembagaId: $lembaga->id, jenisShiftId: $jenisShift->id, tanggalMulai: '2026-12-01', tanggalSelesai: '2026-12-07',
    )))->toThrow(\App\Domains\Sdm\Exceptions\ShiftAssignmentOverlapException::class);
});

it('allows back-to-back assignments that do not actually overlap', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisShift = JenisShift::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'nama' => 'Shift Pagi', 'jam_masuk' => '06:00', 'jam_pulang' => '14:00']);
    $action = app(AssignShiftAction::class);
    $action->execute($guru, new ShiftAssignmentData(lembagaId: $lembaga->id, jenisShiftId: $jenisShift->id, tanggalMulai: '2026-09-01', tanggalSelesai: '2026-09-07'));

    $kedua = $action->execute($guru, new ShiftAssignmentData(
        lembagaId: $lembaga->id, jenisShiftId: $jenisShift->id, tanggalMulai: '2026-09-08', tanggalSelesai: '2026-09-14',
    ));

    expect($kedua)->not->toBeNull();
    expect(PenugasanShift::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->count())->toBe(2);
});

it('allows overlapping date ranges for two different pegawai', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guruA = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $guruB = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisShift = JenisShift::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'nama' => 'Shift Pagi', 'jam_masuk' => '06:00', 'jam_pulang' => '14:00']);
    $action = app(AssignShiftAction::class);
    $action->execute($guruA, new ShiftAssignmentData(lembagaId: $lembaga->id, jenisShiftId: $jenisShift->id, tanggalMulai: '2026-09-01', tanggalSelesai: '2026-09-07'));

    $penugasan = $action->execute($guruB, new ShiftAssignmentData(
        lembagaId: $lembaga->id, jenisShiftId: $jenisShift->id, tanggalMulai: '2026-09-01', tanggalSelesai: '2026-09-07',
    ));

    expect($penugasan)->not->toBeNull();
});

it('excludes itself from the overlap check when updating via excludingId', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisShift = JenisShift::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'nama' => 'Shift Pagi', 'jam_masuk' => '06:00', 'jam_pulang' => '14:00']);
    $action = app(AssignShiftAction::class);
    $penugasan = $action->execute($guru, new ShiftAssignmentData(lembagaId: $lembaga->id, jenisShiftId: $jenisShift->id, tanggalMulai: '2026-09-01', tanggalSelesai: '2026-09-07'));

    $diperbarui = $action->execute($guru, new ShiftAssignmentData(
        lembagaId: $lembaga->id, jenisShiftId: $jenisShift->id, tanggalMulai: '2026-09-02', tanggalSelesai: '2026-09-08',
    ), excludingId: $penugasan->id);

    expect($diperbarui->id)->toBe($penugasan->id);
    expect($diperbarui->tanggal_selesai->toDateString())->toBe('2026-09-08');
    expect(PenugasanShift::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->count())->toBe(1);
});
