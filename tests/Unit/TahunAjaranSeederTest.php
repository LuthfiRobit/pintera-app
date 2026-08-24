<?php
// tests/Unit/TahunAjaranSeederTest.php

use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\TahunAjaranSeeder;
use Database\Seeders\YayasanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new YayasanSeeder())->run();
    (new LembagaSeeder())->run();
});

it('seeds an inactive 2025/2026 and an active 2026/2027 tahun ajaran for the SD institution', function () {
    (new TahunAjaranSeeder())->run();

    foreach (Lembaga::all() as $lembaga) {
        $lama = TahunAjaran::where('lembaga_id', $lembaga->id)->where('nama', '2025/2026')->first();
        expect($lama)->not->toBeNull();
        expect($lama->status_aktif)->toBeFalse();

        $baru = TahunAjaran::where('lembaga_id', $lembaga->id)->where('nama', '2026/2027')->first();
        expect($baru)->not->toBeNull();
        expect($baru->status_aktif)->toBeTrue();
    }
});

it('is idempotent when run twice', function () {
    (new TahunAjaranSeeder())->run();
    (new TahunAjaranSeeder())->run();

    expect(TahunAjaran::count())->toBe(2);
});
