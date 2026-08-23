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

it('seeds correct mata pelajaran per education level across all K-9 institutions', function () {
    (new MataPelajaranSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    expect(MataPelajaran::where('lembaga_id', $smp->id)->where('nama', 'Matematika')->exists())->toBeTrue();
    expect(MataPelajaran::where('lembaga_id', $smp->id)->where('nama', 'Informatika')->exists())->toBeTrue();

    $sd = Lembaga::where('npsn', '20223333')->first();
    expect(MataPelajaran::where('lembaga_id', $sd->id)->where('nama', 'Ilmu Pengetahuan Alam dan Sosial (IPAS)')->exists())->toBeTrue();

    $tk = Lembaga::where('npsn', '20223322')->first();
    expect(MataPelajaran::where('lembaga_id', $tk->id)->where('nama', 'Nilai Agama dan Moral (NAM)')->exists())->toBeTrue();

    $kb = Lembaga::where('npsn', '20223311')->first();
    expect(MataPelajaran::where('lembaga_id', $kb->id)->where('nama', 'Fisik Motorik (FM)')->exists())->toBeTrue();
});

it('is idempotent when run twice', function () {
    (new MataPelajaranSeeder())->run();
    (new MataPelajaranSeeder())->run();

    // KB (6) + TK (6) + SD (9) + SMP (11) = 32
    expect(MataPelajaran::count())->toBe(32);
});
