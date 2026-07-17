<?php
// tests/Unit/JenisTesMasterSeederTest.php

use App\Models\JenisTesMaster;
use App\Models\Lembaga;
use App\Models\Yayasan;
use Database\Seeders\JenisTesMasterSeeder;
use Database\Seeders\LembagaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Yayasan::factory()->create();
    (new LembagaSeeder())->run();
});

it('seeds jenis tes distinct per lembaga', function () {
    (new JenisTesMasterSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    expect(JenisTesMaster::where('lembaga_id', $smp->id)->where('nama', 'Tes Baca Al-Qur\'an')->exists())->toBeTrue();

    $sma = Lembaga::where('npsn', '20223355')->first();
    expect(JenisTesMaster::where('lembaga_id', $sma->id)->where('nama', 'Tes Potensi Akademik')->exists())->toBeTrue();
    expect(JenisTesMaster::where('lembaga_id', $sma->id)->where('nama', 'Tes Baca Al-Qur\'an')->exists())->toBeFalse();
});

it('is idempotent when run twice', function () {
    (new JenisTesMasterSeeder())->run();
    (new JenisTesMasterSeeder())->run();

    expect(JenisTesMaster::count())->toBe(6);
});
