<?php
// tests/Unit/JenisTagihanSeederTest.php

use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\Yayasan;
use Database\Seeders\JenisTagihanSeeder;
use Database\Seeders\LembagaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Yayasan::factory()->create();
    (new LembagaSeeder())->run();
});

it('seeds Biaya Pendaftaran (not cicilable) and Uang Pangkal (cicilable, max 3) per lembaga across all K-9 institutions', function () {
    (new JenisTagihanSeeder())->run();

    foreach (Lembaga::all() as $lembaga) {
        $pendaftaran = JenisTagihan::where('lembaga_id', $lembaga->id)->where('nama', 'Biaya Pendaftaran')->first();
        expect($pendaftaran)->not->toBeNull();
        expect($pendaftaran->kategori)->toBe('pendaftaran');
        expect($pendaftaran->bisa_dicicil)->toBeFalse();

        $daftarUlang = JenisTagihan::where('lembaga_id', $lembaga->id)->where('nama', 'Uang Pangkal')->first();
        expect($daftarUlang)->not->toBeNull();
        expect($daftarUlang->kategori)->toBe('daftar_ulang');
        expect($daftarUlang->bisa_dicicil)->toBeTrue();
        expect($daftarUlang->maks_cicilan)->toBe(3);
    }
});

it('is idempotent when run twice', function () {
    (new JenisTagihanSeeder())->run();
    (new JenisTagihanSeeder())->run();

    expect(JenisTagihan::count())->toBe(8);
});
