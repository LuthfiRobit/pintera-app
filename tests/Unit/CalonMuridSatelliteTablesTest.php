<?php

use App\Models\AlamatCalonMurid;
use App\Models\CalonMurid;
use App\Models\DataKhususCalonMurid;
use App\Models\DataPeriodikCalonMurid;
use App\Models\KeluargaCalonMurid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('relates alamat, keluarga, data periodik, and data khusus to a calon murid', function () {
    $calonMurid = CalonMurid::factory()->create();

    AlamatCalonMurid::create([
        'calon_murid_id' => $calonMurid->id,
        'alamat_jalan' => 'Jl. Merdeka No. 10',
        'rt' => '001',
        'rw' => '002',
        'desa_kelurahan' => 'Sukamaju',
        'kecamatan' => 'Cibeunying',
        'kabupaten_kota' => 'Bandung',
        'provinsi' => 'Jawa Barat',
        'kode_pos' => '40123',
    ]);

    KeluargaCalonMurid::create([
        'calon_murid_id' => $calonMurid->id,
        'jenis' => 'ayah',
        'nama' => 'Budi Santoso',
        'pekerjaan' => 'Wiraswasta',
    ]);
    KeluargaCalonMurid::create([
        'calon_murid_id' => $calonMurid->id,
        'jenis' => 'ibu',
        'nama' => 'Siti Aminah',
        'pekerjaan' => 'Ibu Rumah Tangga',
    ]);

    DataPeriodikCalonMurid::create([
        'calon_murid_id' => $calonMurid->id,
        'tinggi_badan_cm' => 120,
        'berat_badan_kg' => 25,
    ]);

    DataKhususCalonMurid::create([
        'calon_murid_id' => $calonMurid->id,
        'kepemilikan_kip' => true,
        'nomor_kip' => '1234567890',
    ]);

    $calonMurid->refresh();
    expect($calonMurid->alamat->alamat_jalan)->toBe('Jl. Merdeka No. 10');
    expect($calonMurid->keluarga)->toHaveCount(2);
    expect($calonMurid->keluarga->firstWhere('jenis', 'ayah')->nama)->toBe('Budi Santoso');
    expect($calonMurid->dataPeriodik->tinggi_badan_cm)->toBe(120);
    expect($calonMurid->dataKhusus->kepemilikan_kip)->toBeTrue();
});

it('allows a calon murid to have no data periodik or data khusus (both optional)', function () {
    $calonMurid = CalonMurid::factory()->create();

    expect($calonMurid->dataPeriodik)->toBeNull();
    expect($calonMurid->dataKhusus)->toBeNull();
});
