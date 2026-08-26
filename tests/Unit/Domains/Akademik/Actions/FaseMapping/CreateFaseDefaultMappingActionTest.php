<?php

use App\Domains\Akademik\Actions\FaseMapping\CreateFaseDefaultMappingAction;
use App\Domains\Akademik\DataTransferObjects\FaseDefaultMappingData;
use App\Domains\Akademik\Models\Fase;
use App\Domains\Akademik\Models\FaseDefaultMapping;
use App\Models\Lembaga;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('creates a platform-wide mapping when lembagaId is null', function () {
    $fase = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);

    $mapping = app(CreateFaseDefaultMappingAction::class)->execute(new FaseDefaultMappingData(
        bentukPendidikan: 'SD',
        tingkat: '1',
        faseId: $fase->id,
        lembagaId: null,
    ));

    expect($mapping->fresh()->lembaga_id)->toBeNull();
    expect($mapping->fresh()->bentuk_pendidikan)->toBe('SD');
    expect($mapping->fresh()->tingkat)->toBe('1');
    expect($mapping->fresh()->fase_id)->toBe($fase->id);
});

it('creates a lembaga-specific mapping when lembagaId is provided', function () {
    $fase = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $lembaga = Lembaga::factory()->create();

    $mapping = app(CreateFaseDefaultMappingAction::class)->execute(new FaseDefaultMappingData(
        bentukPendidikan: 'SD',
        tingkat: null,
        faseId: $fase->id,
        lembagaId: $lembaga->id,
    ));

    expect($mapping->fresh()->lembaga_id)->toBe($lembaga->id);
    expect($mapping->fresh()->tingkat)->toBeNull();
});
