<?php
// tests/Unit/TahunAjaranSeederTest.php

use App\Models\Lembaga;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\TahunAjaranSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Yayasan::factory()->create();
    (new LembagaSeeder())->run();
});

it('seeds an inactive 2025/2026 and an active 2026/2027 tahun ajaran per lembaga', function () {
    (new TahunAjaranSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    $lama = TahunAjaran::where('lembaga_id', $smp->id)->where('nama', '2025/2026')->first();
    expect($lama)->not->toBeNull();
    expect($lama->status_aktif)->toBeFalse();

    $baru = TahunAjaran::where('lembaga_id', $smp->id)->where('nama', '2026/2027')->first();
    expect($baru)->not->toBeNull();
    expect($baru->status_aktif)->toBeTrue();

    $sma = Lembaga::where('npsn', '20223355')->first();
    expect(TahunAjaran::where('lembaga_id', $sma->id)->where('status_aktif', true)->exists())->toBeTrue();
});

it('is idempotent when run twice', function () {
    (new TahunAjaranSeeder())->run();
    (new TahunAjaranSeeder())->run();

    expect(TahunAjaran::count())->toBe(4);
});
