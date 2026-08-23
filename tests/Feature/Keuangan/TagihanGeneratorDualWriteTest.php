<?php
// tests/Feature/Keuangan/TagihanGeneratorDualWriteTest.php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\NominalTagihanJalur;
use App\Models\Pendaftaran;
use App\Services\TagihanGenerator;

it('sets tagihable_type and tagihable_id to the pendaftaran when generating a PPDB tagihan', function () {
    $pendaftaran = Pendaftaran::factory()->create();

    $jenisTagihan = JenisTagihan::create([
        'lembaga_id' => $pendaftaran->lembaga_id,
        'nama' => 'Biaya Pendaftaran',
        'kategori' => 'pendaftaran',
        'bisa_dicicil' => false,
    ]);
    NominalTagihanJalur::create([
        'jenis_tagihan_id' => $jenisTagihan->id,
        'jalur_ppdb_id' => $pendaftaran->jalur_ppdb_id,
        'nominal' => 150000,
    ]);

    $tagihan = app(TagihanGenerator::class)->generate($pendaftaran, 'pendaftaran');

    expect($tagihan)->not->toBeNull();
    expect($tagihan->tagihable_type)->toBe(Pendaftaran::class);
    expect($tagihan->tagihable_id)->toBe($pendaftaran->id);
    expect((float) $tagihan->net_amount)->toBe(150000.0);
});
