<?php

use App\Enums\TipeKalenderAkademik;
use App\Models\KalenderAkademik;
use App\Models\Lembaga;
use App\Models\Yayasan;
use App\Services\KalenderAkademikResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('resolves a plain weekday with no calendar entries as a school day', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => [0]]);

    $result = (new KalenderAkademikResolver)->resolve($lembaga, Carbon::parse('2026-08-19')); // Wednesday

    expect($result['libur'])->toBeFalse();
});

it('resolves a Sunday as libur via hari_libur_mingguan when no calendar entry exists', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => [0]]);

    $result = (new KalenderAkademikResolver)->resolve($lembaga, Carbon::parse('2026-08-16')); // Sunday

    expect($result['libur'])->toBeTrue();
    expect($result['alasan'])->toBe('Libur mingguan');
});

it('resolves a Friday as a normal school day for a lembaga whose weekly off-day is Sunday only', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => [0]]);

    $result = (new KalenderAkademikResolver)->resolve($lembaga, Carbon::parse('2026-08-21')); // Friday

    expect($result['libur'])->toBeFalse();
});

it('national calendar entry overrides the weekly recurring default', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => [0]]);
    KalenderAkademik::create(['lembaga_id' => null, 'tanggal' => '2026-08-17', 'nama' => 'Hari Kemerdekaan RI', 'tipe' => 'libur']);

    $result = (new KalenderAkademikResolver)->resolve($lembaga, Carbon::parse('2026-08-17')); // Monday, not a weekly off-day

    expect($result['libur'])->toBeTrue();
    expect($result['alasan'])->toBe('Hari Kemerdekaan RI');
});

it('lembaga-specific override beats the national entry for the same date', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => [0]]);
    KalenderAkademik::create(['lembaga_id' => null, 'tanggal' => '2027-01-01', 'nama' => 'Tahun Baru Masehi', 'tipe' => 'libur']);
    KalenderAkademik::create(['lembaga_id' => $lembaga->id, 'tanggal' => '2027-01-01', 'nama' => 'Tetap Masuk (Kebijakan Internal)', 'tipe' => 'kerja']);

    $result = (new KalenderAkademikResolver)->resolve($lembaga, Carbon::parse('2027-01-01'));

    expect($result['libur'])->toBeFalse();
    expect($result['alasan'])->toBe('Tetap Masuk (Kebijakan Internal)');
});

it('lembaga-specific entry does not leak to a different lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => [0]]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => [0]]);
    KalenderAkademik::create(['lembaga_id' => $lembagaA->id, 'tanggal' => '2027-01-01', 'nama' => 'Tetap Masuk (Kebijakan Internal)', 'tipe' => 'kerja']);
    KalenderAkademik::create(['lembaga_id' => null, 'tanggal' => '2027-01-01', 'nama' => 'Tahun Baru Masehi', 'tipe' => 'libur']);

    $result = (new KalenderAkademikResolver)->resolve($lembagaB, Carbon::parse('2027-01-01'));

    expect($result['libur'])->toBeTrue();
    expect($result['alasan'])->toBe('Tahun Baru Masehi');
});

it('resolves a date in the middle of a multi-day lembaga range as libur', function () {
    $lembaga = Lembaga::factory()->create();
    KalenderAkademik::factory()->create([
        'lembaga_id' => $lembaga->id,
        'tanggal' => '2026-08-23',
        'tanggal_selesai' => '2026-09-01',
        'nama' => 'Libur Maulid',
        'tipe' => TipeKalenderAkademik::Libur,
    ]);

    $hasil = app(KalenderAkademikResolver::class)->resolve($lembaga, Carbon::parse('2026-08-27'));

    expect($hasil)->toBe(['libur' => true, 'alasan' => 'Libur Maulid']);
});

it('resolves the last day of a range (inclusive boundary) as still libur', function () {
    $lembaga = Lembaga::factory()->create();
    KalenderAkademik::factory()->create([
        'lembaga_id' => $lembaga->id,
        'tanggal' => '2026-08-23',
        'tanggal_selesai' => '2026-09-01',
        'tipe' => TipeKalenderAkademik::Libur,
    ]);

    $hasil = app(KalenderAkademikResolver::class)->resolve($lembaga, Carbon::parse('2026-09-01'));

    expect($hasil['libur'])->toBeTrue();
});

it('resolves the day after a range ends as a normal effective day', function () {
    $lembaga = Lembaga::factory()->create(['hari_libur_mingguan' => [0]]);
    KalenderAkademik::factory()->create([
        'lembaga_id' => $lembaga->id,
        'tanggal' => '2026-08-23',
        'tanggal_selesai' => '2026-09-01',
        'tipe' => TipeKalenderAkademik::Libur,
    ]);

    $hasil = app(KalenderAkademikResolver::class)->resolve($lembaga, Carbon::parse('2026-09-02'));

    expect($hasil)->toBe(['libur' => false, 'alasan' => 'Hari efektif belajar']);
});

it('does not match a single-day entry against a later, unrelated date', function () {
    $lembaga = Lembaga::factory()->create(['hari_libur_mingguan' => [0]]);
    KalenderAkademik::factory()->create([
        'lembaga_id' => $lembaga->id,
        'tanggal' => '2026-08-01',
        'tanggal_selesai' => null,
        'nama' => 'Libur Sehari',
        'tipe' => TipeKalenderAkademik::Libur,
    ]);

    $hasil = app(KalenderAkademikResolver::class)->resolve($lembaga, Carbon::parse('2026-08-15'));

    expect($hasil)->toBe(['libur' => false, 'alasan' => 'Hari efektif belajar']);
});
