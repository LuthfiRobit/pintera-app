<?php

use App\Models\EkstrakurikulerLembaga;
use App\Models\Lembaga;
use App\Models\LayananKhususLembaga;
use App\Models\ProgramInklusiLembaga;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('relates layanan khusus, program inklusi, and ekstrakurikuler to a lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    LayananKhususLembaga::withoutGlobalScopes()->create([
        'lembaga_id' => $lembaga->id,
        'jenis_layanan' => 'Antar Jemput',
    ]);

    ProgramInklusiLembaga::withoutGlobalScopes()->create([
        'lembaga_id' => $lembaga->id,
        'kebutuhan_khusus' => 'Tunanetra',
    ]);

    EkstrakurikulerLembaga::withoutGlobalScopes()->create([
        'lembaga_id' => $lembaga->id,
        'jenis_ekskul' => 'Olahraga',
        'nama_ekskul' => 'Futsal',
    ]);

    expect($lembaga->layananKhusus)->toHaveCount(1);
    expect($lembaga->programInklusi)->toHaveCount(1);
    expect($lembaga->ekstrakurikuler)->toHaveCount(1);
});
