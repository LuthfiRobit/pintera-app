<?php

use App\Domains\Akademik\Models\Fase;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('allows a Kelas to be created without fase_id (backward compatible, default null)', function () {
    $lembaga = Lembaga::factory()->create();
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);

    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

    expect($kelas->fresh()->fase_id)->toBeNull();
});

it('stores and resolves the fase relation when fase_id is set', function () {
    $fase = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $lembaga = Lembaga::factory()->create();
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);

    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'fase_id' => $fase->id]);

    expect($kelas->fresh()->fase->nama)->toBe('Fase A');
});

it('keeps Kelas.fase_id unchanged even after the Fase row it points to is edited', function () {
    $fase = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $lembaga = Lembaga::factory()->create();
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'fase_id' => $fase->id]);

    $fase->update(['nama' => 'Fase A (direvisi)']);

    expect($kelas->fresh()->fase_id)->toBe($fase->id);
    expect($kelas->fresh()->fase->nama)->toBe('Fase A (direvisi)');
});
