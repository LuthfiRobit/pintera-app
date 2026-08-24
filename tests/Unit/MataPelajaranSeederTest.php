<?php
// tests/Unit/MataPelajaranSeederTest.php

use App\Models\Lembaga;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Models\Yayasan;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\MataPelajaranSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Yayasan::factory()->create();
    (new LembagaSeeder())->run();
});

it('seeds correct mata pelajaran for the SD institution', function () {
    (new MataPelajaranSeeder())->run();

    $sd = Lembaga::where('npsn', '20223333')->first();
    expect(MataPelajaran::where('lembaga_id', $sd->id)->where('nama', 'Ilmu Pengetahuan Alam dan Sosial (IPAS)')->exists())->toBeTrue();
    expect(MataPelajaran::where('lembaga_id', $sd->id)->where('nama', 'Matematika')->exists())->toBeTrue();
});

it('is idempotent when run twice', function () {
    (new MataPelajaranSeeder())->run();
    (new MataPelajaranSeeder())->run();

    // SD saja: 9
    expect(MataPelajaran::count())->toBe(9);
});
