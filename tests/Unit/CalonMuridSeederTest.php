<?php
// tests/Unit/CalonMuridSeederTest.php

use App\Models\CalonMurid;
use App\Models\Lembaga;
use App\Models\Yayasan;
use Database\Seeders\CalonMuridSeeder;
use Database\Seeders\LembagaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Yayasan::factory()->create();
    (new LembagaSeeder())->run();
});

it('seeds 4 calon per lembaga with lembaga-qualified names', function () {
    (new CalonMuridSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();

    expect(CalonMurid::where('nama_lengkap', 'Calon Menunggu Verifikasi ('.$smp->nama.')')->exists())->toBeTrue();
    expect(CalonMurid::where('nama_lengkap', 'Calon Diterima ('.$smp->nama.')')->exists())->toBeTrue();
    expect(CalonMurid::where('nama_lengkap', 'Calon Ditolak ('.$smp->nama.')')->exists())->toBeTrue();
    expect(CalonMurid::where('nama_lengkap', 'Calon Cicilan Demo ('.$smp->nama.')')->exists())->toBeTrue();
});

it('is idempotent when run twice', function () {
    (new CalonMuridSeeder())->run();
    (new CalonMuridSeeder())->run();

    expect(CalonMurid::count())->toBe(8);
});
