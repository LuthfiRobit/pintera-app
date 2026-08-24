<?php
// tests/Unit/LembagaSeederTest.php

use App\Models\Lembaga;
use App\Models\Yayasan;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\YayasanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('seeds yayasan pintera and the single SD institution', function () {
    (new YayasanSeeder())->run();
    (new LembagaSeeder())->run();

    $yayasan = Yayasan::where('nama', 'Yayasan Pintera')->first();
    expect($yayasan)->not->toBeNull();
    expect($yayasan->alamat)->toContain('Kraksaan', 'Probolinggo', 'Jawa Timur');

    expect(Lembaga::count())->toBe(1);
    expect(Lembaga::where('npsn', '20223333')->value('nama'))->toBe('SDIT PINTERA');
    expect(Lembaga::where('npsn', '20223333')->value('kode_lembaga'))->toBe('SDITPTR');
    expect(Lembaga::where('npsn', '20223311')->exists())->toBeFalse();
    expect(Lembaga::where('npsn', '20223322')->exists())->toBeFalse();
    expect(Lembaga::where('npsn', '20223344')->exists())->toBeFalse();
});

it('is idempotent when run twice', function () {
    (new YayasanSeeder())->run();

    (new LembagaSeeder())->run();
    (new LembagaSeeder())->run();

    expect(Lembaga::count())->toBe(1);
});
