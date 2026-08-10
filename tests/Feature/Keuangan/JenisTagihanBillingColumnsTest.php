<?php

use App\Models\JenisTagihan;
use App\Models\Lembaga;

it('stores mode otomatis scheduling fields for a spp jenis_tagihan', function () {
    $jenisTagihan = JenisTagihan::create([
        'lembaga_id' => Lembaga::factory()->create()->id,
        'nama' => 'SPP Bulanan',
        'kategori' => 'spp',
        'bisa_dicicil' => false,
        'priority_score' => 1,
        'default_amount' => 300000,
        'mode' => 'otomatis',
        'tanggal_mulai' => '2026-08-01',
        'tanggal_generate' => 1,
        'hari_jatuh_tempo' => 10,
    ]);

    expect($jenisTagihan->mode)->toBe('otomatis');
    expect($jenisTagihan->is_active)->toBeTrue();
    expect((float) $jenisTagihan->default_amount)->toBe(300000.0);
});

it('defaults mode to manual and is_active to true when not specified', function () {
    $jenisTagihan = JenisTagihan::factory()->create();

    expect($jenisTagihan->mode)->toBe('manual');
    expect($jenisTagihan->is_active)->toBeTrue();
});
