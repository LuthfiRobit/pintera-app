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

it('seeds jenis tes distinct per lembaga according to education level', function () {
    (new JenisTesMasterSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    expect(JenisTesMaster::where('lembaga_id', $smp->id)->where('nama', 'Tes Baca Al-Qur\'an')->exists())->toBeTrue();

    $sdit = Lembaga::where('npsn', '20223333')->first();
    expect(JenisTesMaster::where('lembaga_id', $sdit->id)->where('nama', 'Observasi Kesiapan Sekolah')->exists())->toBeTrue();

    $kbit = Lembaga::where('npsn', '20223311')->first();
    expect(JenisTesMaster::where('lembaga_id', $kbit->id)->where('nama', 'Observasi Anak')->exists())->toBeTrue();
    expect(JenisTesMaster::where('lembaga_id', $kbit->id)->where('nama', 'Tes Tulis')->exists())->toBeFalse();
});

it('is idempotent when run twice', function () {
    (new JenisTesMasterSeeder())->run();
    (new JenisTesMasterSeeder())->run();

    // KB(2) + TK(2) + SD(3) + SMP(3) = 10
    expect(JenisTesMaster::count())->toBe(10);
});
