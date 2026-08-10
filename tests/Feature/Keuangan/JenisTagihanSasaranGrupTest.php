<?php

use App\Models\JenisTagihan;
use App\Models\JenisTagihanSasaranGrup;
use App\Models\JenisTagihanSasaranKriteria;

it('stores a sasaran grup with AND-ed criteria rows under one jenis_tagihan', function () {
    $jenisTagihan = JenisTagihan::factory()->create();

    $grup = JenisTagihanSasaranGrup::create([
        'jenis_tagihan_id' => $jenisTagihan->id,
        'tipe' => 'sasaran',
    ]);

    JenisTagihanSasaranKriteria::create([
        'jenis_tagihan_sasaran_grup_id' => $grup->id,
        'field' => 'kelas',
        'operator' => 'in',
        'value' => [1, 2],
    ]);

    expect($grup->tipe)->toBe('sasaran');
    expect($grup->kriteria)->toHaveCount(1);
    expect($grup->kriteria->first()->value)->toBe([1, 2]);
});

it('stores a tarif grup with a nominal override', function () {
    $jenisTagihan = JenisTagihan::factory()->create();

    $grup = JenisTagihanSasaranGrup::create([
        'jenis_tagihan_id' => $jenisTagihan->id,
        'tipe' => 'tarif',
        'nominal' => 500000,
    ]);

    expect((float) $grup->nominal)->toBe(500000.0);
});
