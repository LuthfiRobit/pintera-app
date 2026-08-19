<?php

use App\Domains\Akademik\Models\CatatanWaliKelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\Yayasan;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

it('derives lembaga_id from siswa on create and casts json columns to array', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);

    $catatan = CatatanWaliKelas::factory()->create([
        'siswa_id' => $siswa->id,
        'ekstrakurikuler' => [['nama' => 'Pramuka', 'predikat' => 'A']],
        'tinggi_badan_cm' => 110.5,
    ]);

    expect($catatan->lembaga_id)->toBe($lembaga->id);
    expect($catatan->ekstrakurikuler)->toBe([['nama' => 'Pramuka', 'predikat' => 'A']]);
    expect((float) $catatan->tinggi_badan_cm)->toBe(110.5);
});
