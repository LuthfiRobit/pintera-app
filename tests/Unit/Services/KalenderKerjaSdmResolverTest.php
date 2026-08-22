<?php
// tests/Unit/Services/KalenderKerjaSdmResolverTest.php

use App\Domains\Sdm\Enums\TipeKalenderKerjaSdm;
use App\Domains\Sdm\Models\KalenderKerjaSdm;
use App\Domains\Sdm\Services\KalenderKerjaSdmResolver;
use App\Models\Lembaga;
use App\Models\Yayasan;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('resolves a plain weekday with no calendar entries as a work day', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);

    $result = (new KalenderKerjaSdmResolver)->resolve($lembaga, Carbon::parse('2026-08-19')); // Wednesday

    expect($result['libur'])->toBeFalse();
});

it('resolves a Sunday as libur via hari_libur_mingguan_sdm when no calendar entry exists', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);

    $result = (new KalenderKerjaSdmResolver)->resolve($lembaga, Carbon::parse('2026-08-16')); // Sunday

    expect($result['libur'])->toBeTrue();
    expect($result['alasan'])->toBe('Libur mingguan SDM');
});

it('national calendar entry overrides the weekly recurring default', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    KalenderKerjaSdm::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => null, 'tanggal' => '2026-08-17', 'nama' => 'Hari Kemerdekaan RI', 'tipe' => TipeKalenderKerjaSdm::Libur]);

    $result = (new KalenderKerjaSdmResolver)->resolve($lembaga, Carbon::parse('2026-08-17')); // Monday, not a weekly off-day

    expect($result['libur'])->toBeTrue();
    expect($result['alasan'])->toBe('Hari Kemerdekaan RI');
});

it('lembaga-specific override beats the national entry for the same date', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    KalenderKerjaSdm::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => null, 'tanggal' => '2027-01-01', 'nama' => 'Tahun Baru Masehi', 'tipe' => TipeKalenderKerjaSdm::Libur]);
    KalenderKerjaSdm::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'tanggal' => '2027-01-01', 'nama' => 'TU Tetap Masuk', 'tipe' => TipeKalenderKerjaSdm::Kerja]);

    $result = (new KalenderKerjaSdmResolver)->resolve($lembaga, Carbon::parse('2027-01-01'));

    expect($result['libur'])->toBeFalse();
    expect($result['alasan'])->toBe('TU Tetap Masuk');
});

it('lembaga-specific entry does not leak to a different lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    KalenderKerjaSdm::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembagaA->id, 'tanggal' => '2027-01-01', 'nama' => 'TU Tetap Masuk', 'tipe' => TipeKalenderKerjaSdm::Kerja]);
    KalenderKerjaSdm::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => null, 'tanggal' => '2027-01-01', 'nama' => 'Tahun Baru Masehi', 'tipe' => TipeKalenderKerjaSdm::Libur]);

    $result = (new KalenderKerjaSdmResolver)->resolve($lembagaB, Carbon::parse('2027-01-01'));

    expect($result['libur'])->toBeTrue();
    expect($result['alasan'])->toBe('Tahun Baru Masehi');
});

it('resolves a date in the middle of a multi-day lembaga range as libur', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    KalenderKerjaSdm::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'tanggal' => '2026-08-23', 'tanggal_selesai' => '2026-09-01', 'nama' => 'Libur Maulid', 'tipe' => TipeKalenderKerjaSdm::Libur]);

    $result = (new KalenderKerjaSdmResolver)->resolve($lembaga, Carbon::parse('2026-08-27'));

    expect($result)->toBe(['libur' => true, 'alasan' => 'Libur Maulid']);
});

it('resolves the day after a range ends as a normal work day', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    KalenderKerjaSdm::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'tanggal' => '2026-08-23', 'tanggal_selesai' => '2026-09-01', 'nama' => 'Libur Maulid', 'tipe' => TipeKalenderKerjaSdm::Libur]);

    $result = (new KalenderKerjaSdmResolver)->resolve($lembaga, Carbon::parse('2026-09-02'));

    expect($result)->toBe(['libur' => false, 'alasan' => 'Hari kerja efektif']);
});
