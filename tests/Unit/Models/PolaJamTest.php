<?php

use App\Models\Lembaga;
use App\Domains\Akademik\Models\PolaJam;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('belongs to a lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $pola = PolaJam::create(['lembaga_id' => $lembaga->id, 'nama' => 'Kelas Tinggi 4-6']);

    expect($pola->fresh()->lembaga->id)->toBe($lembaga->id);
});
