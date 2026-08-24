<?php
// tests/Unit/PolaJamSeederTest.php

use App\Models\Lembaga;
use App\Domains\Akademik\Models\PolaJam;
use App\Models\Yayasan;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\PolaJamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Yayasan::factory()->create();
    (new LembagaSeeder())->run();
});

it('seeds one pola jam for the SD institution', function () {
    (new PolaJamSeeder())->run();

    foreach (Lembaga::all() as $lembaga) {
        $polaJam = PolaJam::where('lembaga_id', $lembaga->id)->first();
        expect($polaJam)->not->toBeNull();
        expect($polaJam->nama)->toBe('Pola Jam '.$lembaga->bentuk_pendidikan);
    }
});

it('is idempotent when run twice', function () {
    (new PolaJamSeeder())->run();
    (new PolaJamSeeder())->run();

    expect(PolaJam::count())->toBe(1);
});
