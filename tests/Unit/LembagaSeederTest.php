<?php
// tests/Unit/LembagaSeederTest.php

use App\Models\Lembaga;
use App\Models\Yayasan;
use Database\Seeders\LembagaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('seeds SMP and SMA with the expected identifying fields', function () {
    Yayasan::factory()->create(['nama' => 'Yayasan Pendidikan Islam Al-Hikmah']);

    (new LembagaSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    expect($smp)->not->toBeNull();
    expect($smp->nama)->toBe('SMP Islam Al-Hikmah');
    expect($smp->bentuk_pendidikan)->toBe('SMP');
    expect($smp->status_aktif)->toBeTrue();

    $sma = Lembaga::where('npsn', '20223355')->first();
    expect($sma)->not->toBeNull();
    expect($sma->nama)->toBe('SMA Islam Al-Hikmah');
    expect($sma->bentuk_pendidikan)->toBe('SMA');
});

it('is idempotent when run twice', function () {
    Yayasan::factory()->create();

    (new LembagaSeeder())->run();
    (new LembagaSeeder())->run();

    expect(Lembaga::count())->toBe(2);
});
