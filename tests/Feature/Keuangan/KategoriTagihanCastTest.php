<?php

use App\Domains\Keuangan\Enums\KategoriTagihan;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\Lembaga;

it('casts jenis_tagihan.kategori to KategoriTagihan', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['kategori' => 'spp']);

    expect($jenisTagihan->fresh()->kategori)->toBe(KategoriTagihan::Spp);
});

it('serializes the enum cast back to its raw string value in toArray/toJson', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['kategori' => 'spp']);

    expect($jenisTagihan->fresh()->toArray()['kategori'])->toBe('spp');
    expect(json_decode($jenisTagihan->fresh()->toJson(), true)['kategori'])->toBe('spp');
});

it('accepts a plain string on create/update despite the cast', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['kategori' => 'tahunan']);

    expect($jenisTagihan->fresh()->kategori)->toBe(KategoriTagihan::Tahunan);
});
