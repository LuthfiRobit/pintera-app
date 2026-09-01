<?php

use App\Domains\Keuangan\Actions\JenisTagihan\UpdateJenisTagihanAction;
use App\Domains\Keuangan\DataTransferObjects\JenisTagihanData;
use App\Domains\Keuangan\Models\JenisTagihan;

it('nulls out fields owned by the old tipe when moving from Mingguan to Bulanan', function () {
    $jenisTagihan = JenisTagihan::factory()->create([
        'mode' => 'otomatis', 'tipe' => 'mingguan', 'tanggal_mulai' => now()->toDateString(),
        'hari_generate' => 3, 'offset_hari_jatuh_tempo' => 5,
    ]);

    $data = JenisTagihanData::fromArray([
        'nama' => $jenisTagihan->nama, 'kategori' => 'spp', 'mode' => 'otomatis', 'tipe' => 'bulanan',
        'tanggal_mulai' => now()->toDateString(), 'tanggal_generate' => 10, 'hari_jatuh_tempo' => 20,
    ]);

    app(UpdateJenisTagihanAction::class)->execute($jenisTagihan, $data);

    $fresh = $jenisTagihan->fresh();
    expect($fresh->hari_generate)->toBeNull();
    expect($fresh->offset_hari_jatuh_tempo)->toBeNull();
    expect($fresh->tanggal_generate)->toBe(10);
    expect($fresh->hari_jatuh_tempo)->toBe(20);
});

it('nulls out fields owned by the old tipe when moving from Tahunan to Harian', function () {
    $jenisTagihan = JenisTagihan::factory()->create([
        'mode' => 'otomatis', 'tipe' => 'tahunan', 'tanggal_mulai' => now()->toDateString(),
        'bulan_generate' => 7, 'tanggal_generate' => 1, 'hari_jatuh_tempo' => 15,
    ]);

    $data = JenisTagihanData::fromArray([
        'nama' => $jenisTagihan->nama, 'kategori' => 'spp', 'mode' => 'otomatis', 'tipe' => 'harian',
        'tanggal_mulai' => now()->toDateString(), 'offset_hari_jatuh_tempo' => 2,
    ]);

    app(UpdateJenisTagihanAction::class)->execute($jenisTagihan, $data);

    $fresh = $jenisTagihan->fresh();
    expect($fresh->bulan_generate)->toBeNull();
    expect($fresh->tanggal_generate)->toBeNull();
    expect($fresh->hari_jatuh_tempo)->toBeNull();
    expect($fresh->offset_hari_jatuh_tempo)->toBe(2);
});
