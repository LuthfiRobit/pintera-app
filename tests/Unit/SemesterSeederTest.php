<?php
// tests/Unit/SemesterSeederTest.php

use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\SemesterSeeder;
use Database\Seeders\TahunAjaranSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Yayasan::factory()->create();
    (new LembagaSeeder())->run();
    (new TahunAjaranSeeder())->run();
});

it('seeds Ganjil and Genap semester for every tahun ajaran, active semester matching its tahun ajaran', function () {
    (new SemesterSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    $aktif = TahunAjaran::where('lembaga_id', $smp->id)->where('status_aktif', true)->first();

    $ganjil = Semester::where('tahun_ajaran_id', $aktif->id)->where('nama', 'Ganjil')->first();
    expect($ganjil)->not->toBeNull();
    expect($ganjil->status_aktif)->toBeTrue();
    expect($ganjil->urutan)->toBe(1);

    $genap = Semester::where('tahun_ajaran_id', $aktif->id)->where('nama', 'Genap')->first();
    expect($genap)->not->toBeNull();
    expect($genap->status_aktif)->toBeFalse();
});

it('is idempotent when run twice', function () {
    (new SemesterSeeder())->run();
    (new SemesterSeeder())->run();

    expect(Semester::count())->toBe(8);
});
