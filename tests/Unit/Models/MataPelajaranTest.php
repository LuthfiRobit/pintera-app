<?php

use App\Enums\TipeMataPelajaran;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('casts tipe to the TipeMataPelajaran enum', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $mapel = MataPelajaran::create([
        'lembaga_id' => $lembaga->id,
        'nama' => 'Matematika',
        'tipe' => TipeMataPelajaran::Mapel->value,
    ]);

    expect($mapel->fresh()->tipe)->toBe(TipeMataPelajaran::Mapel);
});

it('belongs to a lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);

    expect($mapel->lembaga->id)->toBe($lembaga->id);
});
