<?php
// tests/Unit/SemesterSeederTest.php

use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\TahunAjaran;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\SemesterSeeder;
use Database\Seeders\TahunAjaranSeeder;
use Database\Seeders\YayasanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new YayasanSeeder())->run();
    (new LembagaSeeder())->run();
    (new TahunAjaranSeeder())->run();
});

it('seeds Ganjil and Genap semester for the SD institution', function () {
    (new SemesterSeeder())->run();

    foreach (Lembaga::all() as $lembaga) {
        $aktif = TahunAjaran::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first();
        expect($aktif)->not->toBeNull();

        $ganjil = Semester::where('tahun_ajaran_id', $aktif->id)->where('nama', 'Ganjil')->first();
        expect($ganjil)->not->toBeNull();
        expect($ganjil->status_aktif)->toBeTrue();
        expect($ganjil->urutan)->toBe(1);

        $genap = Semester::where('tahun_ajaran_id', $aktif->id)->where('nama', 'Genap')->first();
        expect($genap)->not->toBeNull();
        expect($genap->status_aktif)->toBeFalse();
    }
});

it('is idempotent when run twice', function () {
    (new SemesterSeeder())->run();
    (new SemesterSeeder())->run();

    expect(Semester::count())->toBe(4);
});
