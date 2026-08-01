<?php

use App\Enums\KelompokMataPelajaran;
use App\Enums\StatusMataPelajaran;
use App\Enums\TipeMataPelajaran;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('casts tipe, kelompok, and status to their respective enums', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $mapel = MataPelajaran::create([
        'lembaga_id' => $lembaga->id,
        'kode' => 'MTK-01',
        'nama' => 'Matematika',
        'no_urut' => 1,
        'tipe' => TipeMataPelajaran::Mapel->value,
        'kelompok' => KelompokMataPelajaran::Umum->value,
        'status' => StatusMataPelajaran::Aktif->value,
    ]);

    $fresh = $mapel->fresh();
    expect($fresh->tipe)->toBe(TipeMataPelajaran::Mapel);
    expect($fresh->kelompok)->toBe(KelompokMataPelajaran::Umum);
    expect($fresh->status)->toBe(StatusMataPelajaran::Aktif);
    expect($fresh->kode)->toBe('MTK-01');
    expect($fresh->no_urut)->toBe(1);
});

it('allows nullable kelompok for aspek perkembangan paud', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $mapel = MataPelajaran::create([
        'lembaga_id' => $lembaga->id,
        'kode' => 'PAUD-01',
        'nama' => 'Nilai Agama dan Moral',
        'no_urut' => 1,
        'tipe' => TipeMataPelajaran::AspekPerkembangan->value,
        'kelompok' => null,
        'status' => StatusMataPelajaran::Aktif->value,
    ]);

    expect($mapel->fresh()->kelompok)->toBeNull();
});

it('belongs to a lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);

    expect($mapel->lembaga->id)->toBe($lembaga->id);
});
