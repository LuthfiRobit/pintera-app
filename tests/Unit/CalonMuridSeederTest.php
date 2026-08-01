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

it('seeds 4 calon per lembaga across all K-9 institutions with lembaga-qualified names', function () {
    (new CalonMuridSeeder())->run();

    foreach (Lembaga::all() as $lembaga) {
        expect(CalonMurid::where('nama_lengkap', 'Calon Menunggu Verifikasi ('.$lembaga->nama.')')->exists())->toBeTrue();
        expect(CalonMurid::where('nama_lengkap', 'Calon Diterima ('.$lembaga->nama.')')->exists())->toBeTrue();
        expect(CalonMurid::where('nama_lengkap', 'Calon Ditolak ('.$lembaga->nama.')')->exists())->toBeTrue();
        expect(CalonMurid::where('nama_lengkap', 'Calon Cicilan Demo ('.$lembaga->nama.')')->exists())->toBeTrue();
    }
});

it('is idempotent when run twice', function () {
    (new CalonMuridSeeder())->run();
    (new CalonMuridSeeder())->run();

    expect(CalonMurid::count())->toBe(16);
});
