<?php
// tests/Unit/TugasBatchGeneratorTest.php

use App\Services\TugasBatchGenerator;
use Carbon\Carbon;

beforeEach(function () {
    $this->generator = new TugasBatchGenerator();
});

it('keeps sekali as sekali regardless of range length', function () {
    $hasil = $this->generator->tentukanFrekuensiAkhir('sekali', Carbon::parse('2026-08-01'), Carbon::parse('2026-12-31'));
    expect($hasil)->toBe('sekali');
});

it('keeps harian as harian regardless of range length', function () {
    $hasil = $this->generator->tentukanFrekuensiAkhir('harian', Carbon::parse('2026-08-01'), Carbon::parse('2026-08-01'));
    expect($hasil)->toBe('harian');
});

it('falls back mingguan to harian when the range is 7 days or less', function () {
    $hasil = $this->generator->tentukanFrekuensiAkhir('mingguan', Carbon::parse('2026-08-01'), Carbon::parse('2026-08-08'));
    expect($hasil)->toBe('harian');
});

it('keeps mingguan as mingguan when the range is more than 7 days', function () {
    $hasil = $this->generator->tentukanFrekuensiAkhir('mingguan', Carbon::parse('2026-08-01'), Carbon::parse('2026-08-09'));
    expect($hasil)->toBe('mingguan');
});

it('falls back bulanan to mingguan when the range is 30 days or less', function () {
    $hasil = $this->generator->tentukanFrekuensiAkhir('bulanan', Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'));
    expect($hasil)->toBe('mingguan');
});

it('chains bulanan all the way down to harian when the range is 7 days or less', function () {
    $hasil = $this->generator->tentukanFrekuensiAkhir('bulanan', Carbon::parse('2026-08-01'), Carbon::parse('2026-08-05'));
    expect($hasil)->toBe('harian');
});

it('keeps bulanan as bulanan when the range is more than 30 days', function () {
    $hasil = $this->generator->tentukanFrekuensiAkhir('bulanan', Carbon::parse('2026-08-01'), Carbon::parse('2026-09-02'));
    expect($hasil)->toBe('bulanan');
});

it('generates one row for sekali spanning the whole range', function () {
    $baris = $this->generator->generate('sekali', Carbon::parse('2026-08-01'), Carbon::parse('2026-08-20'));

    expect($baris)->toHaveCount(1);
    expect($baris[0]['mulai_pada']->toDateString())->toBe('2026-08-01');
    expect($baris[0]['batas_selesai_pada']->toDateString())->toBe('2026-08-20');
});

it('generates one row per calendar day for harian', function () {
    $baris = $this->generator->generate('harian', Carbon::parse('2026-08-10'), Carbon::parse('2026-08-12'));

    expect($baris)->toHaveCount(3);
    expect($baris[0]['mulai_pada']->toDateString())->toBe('2026-08-10');
    expect($baris[0]['batas_selesai_pada']->toDateString())->toBe('2026-08-10');
    expect($baris[1]['mulai_pada']->toDateString())->toBe('2026-08-11');
    expect($baris[2]['mulai_pada']->toDateString())->toBe('2026-08-12');
});

it('falls back mingguan to harian rows when the range is too short', function () {
    $baris = $this->generator->generate('mingguan', Carbon::parse('2026-08-01'), Carbon::parse('2026-08-05'));

    expect($baris)->toHaveCount(5);
    expect($baris[0]['mulai_pada']->toDateString())->toBe($baris[0]['batas_selesai_pada']->toDateString());
});

it('generates 7-day blocks for mingguan with a trailing short block, not merged into the last full block', function () {
    // 1-31 Agustus = 30 hari selisih, per contoh spesifikasi user.
    $baris = $this->generator->generate('mingguan', Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'));

    expect($baris)->toHaveCount(5);
    expect([$baris[0]['mulai_pada']->toDateString(), $baris[0]['batas_selesai_pada']->toDateString()])->toBe(['2026-08-01', '2026-08-07']);
    expect([$baris[1]['mulai_pada']->toDateString(), $baris[1]['batas_selesai_pada']->toDateString()])->toBe(['2026-08-08', '2026-08-14']);
    expect([$baris[2]['mulai_pada']->toDateString(), $baris[2]['batas_selesai_pada']->toDateString()])->toBe(['2026-08-15', '2026-08-21']);
    expect([$baris[3]['mulai_pada']->toDateString(), $baris[3]['batas_selesai_pada']->toDateString()])->toBe(['2026-08-22', '2026-08-28']);
    // Blok terakhir hanya 3 hari (29-31), TERPISAH — bukan digabung jadi blok ke-4 yang 10 hari.
    expect([$baris[4]['mulai_pada']->toDateString(), $baris[4]['batas_selesai_pada']->toDateString()])->toBe(['2026-08-29', '2026-08-31']);
});

it('generates exactly 7-day blocks with no trailing block when the range divides evenly', function () {
    $baris = $this->generator->generate('mingguan', Carbon::parse('2026-08-01'), Carbon::parse('2026-08-14'));

    expect($baris)->toHaveCount(2);
    expect([$baris[1]['mulai_pada']->toDateString(), $baris[1]['batas_selesai_pada']->toDateString()])->toBe(['2026-08-08', '2026-08-14']);
});

it('falls back bulanan to mingguan-shaped rows when the range is too short for monthly', function () {
    $baris = $this->generator->generate('bulanan', Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'));

    expect($baris)->toHaveCount(5);
});

it('generates one row per month segment for bulanan, due date clamped to the day of month', function () {
    $baris = $this->generator->generate('bulanan', Carbon::parse('2026-08-01'), Carbon::parse('2026-11-30'), tanggalPengumpulanBulanan: 15);

    expect($baris)->toHaveCount(5);
    expect([$baris[0]['mulai_pada']->toDateString(), $baris[0]['batas_selesai_pada']->toDateString()])->toBe(['2026-08-01', '2026-08-15']);
    expect([$baris[1]['mulai_pada']->toDateString(), $baris[1]['batas_selesai_pada']->toDateString()])->toBe(['2026-08-16', '2026-09-15']);
    expect([$baris[2]['mulai_pada']->toDateString(), $baris[2]['batas_selesai_pada']->toDateString()])->toBe(['2026-09-16', '2026-10-15']);
    expect([$baris[3]['mulai_pada']->toDateString(), $baris[3]['batas_selesai_pada']->toDateString()])->toBe(['2026-10-16', '2026-11-15']);
    // Baris terakhir dipotong (min()) ke tanggal_selesai keseluruhan (30 Nov), bukan Des 15.
    expect([$baris[4]['mulai_pada']->toDateString(), $baris[4]['batas_selesai_pada']->toDateString()])->toBe(['2026-11-16', '2026-11-30']);
});

it('generates bulanan rows using end-of-month due dates, correctly handling February', function () {
    $baris = $this->generator->generate('bulanan', Carbon::parse('2026-01-01'), Carbon::parse('2026-04-30'), akhirBulan: true);

    expect($baris)->toHaveCount(4);
    expect($baris[0]['batas_selesai_pada']->toDateString())->toBe('2026-01-31');
    expect($baris[1]['batas_selesai_pada']->toDateString())->toBe('2026-02-28');
    expect($baris[2]['batas_selesai_pada']->toDateString())->toBe('2026-03-31');
    expect($baris[3]['batas_selesai_pada']->toDateString())->toBe('2026-04-30');
});

it('pushes the first monthly due date to the next month when the range starts after that day of month', function () {
    // Mulai 20 Agustus, tanggal_pengumpulan_bulanan=15 -> jatuh tempo pertama BUKAN 15 Agustus
    // (sudah lewat relatif terhadap tanggal_mulai), melainkan 15 September.
    $baris = $this->generator->generate('bulanan', Carbon::parse('2026-08-20'), Carbon::parse('2026-11-30'), tanggalPengumpulanBulanan: 15);

    expect($baris[0]['batas_selesai_pada']->toDateString())->toBe('2026-09-15');
});
