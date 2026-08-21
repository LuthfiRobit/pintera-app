<?php
// tests/Unit/LembagaSeederTest.php

use App\Models\Lembaga;
use App\Models\Yayasan;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\YayasanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('seeds yayasan pintera and 4 K-9 institutions without SMA', function () {
    (new YayasanSeeder())->run();
    (new LembagaSeeder())->run();

    $yayasan = Yayasan::where('nama', 'Yayasan Pintera')->first();
    expect($yayasan)->not->toBeNull();
    expect($yayasan->alamat)->toContain('Kraksaan', 'Probolinggo', 'Jawa Timur');

    expect(Lembaga::count())->toBe(4);
    expect(Lembaga::where('npsn', '20223311')->value('nama'))->toBe('KBIT PINTERA');
    expect(Lembaga::where('npsn', '20223322')->value('nama'))->toBe('TKIT PINTERA');
    expect(Lembaga::where('npsn', '20223333')->value('nama'))->toBe('SDIT PINTERA');
    expect(Lembaga::where('npsn', '20223344')->value('nama'))->toBe('SMPIT PINTERA');
    expect(Lembaga::where('npsn', '20223355')->exists())->toBeFalse();
});

it('is idempotent when run twice', function () {
    (new YayasanSeeder())->run();

    (new LembagaSeeder())->run();
    (new LembagaSeeder())->run();

    expect(Lembaga::count())->toBe(4);
});
