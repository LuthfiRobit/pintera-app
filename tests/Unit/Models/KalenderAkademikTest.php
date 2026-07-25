<?php

use App\Enums\TipeKalenderAkademik;
use App\Models\KalenderAkademik;
use App\Models\Lembaga;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('allows a null lembaga_id to represent a national entry', function () {
    $entry = KalenderAkademik::create([
        'lembaga_id' => null,
        'tanggal' => '2026-08-17',
        'nama' => 'Hari Kemerdekaan RI',
        'tipe' => TipeKalenderAkademik::Libur->value,
    ]);

    expect($entry->fresh()->lembaga_id)->toBeNull();
    expect($entry->fresh()->tipe)->toBe(TipeKalenderAkademik::Libur);
});

it('can belong to a specific lembaga as an override', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $entry = KalenderAkademik::create([
        'lembaga_id' => $lembaga->id,
        'tanggal' => '2026-01-01',
        'nama' => 'Tetap Masuk (Kebijakan Internal)',
        'tipe' => TipeKalenderAkademik::Kerja->value,
    ]);

    expect($entry->fresh()->lembaga->id)->toBe($lembaga->id);
});
