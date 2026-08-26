<?php

use App\Domains\Akademik\Actions\FaseMapping\UpdateFaseDefaultMappingAction;
use App\Domains\Akademik\DataTransferObjects\FaseDefaultMappingData;
use App\Domains\Akademik\Models\Fase;
use App\Domains\Akademik\Models\FaseDefaultMapping;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('updates bentuk_pendidikan, tingkat, and fase_id without touching lembaga_id', function () {
    $faseA = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $faseB = Fase::create(['kode' => 'b', 'nama' => 'Fase B', 'urutan' => 2]);
    $mapping = FaseDefaultMapping::create(['lembaga_id' => null, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $faseA->id]);

    $hasil = app(UpdateFaseDefaultMappingAction::class)->execute($mapping, new FaseDefaultMappingData(
        bentukPendidikan: 'SD',
        tingkat: '2',
        faseId: $faseB->id,
        lembagaId: null,
    ));

    expect($hasil->fresh()->tingkat)->toBe('2');
    expect($hasil->fresh()->fase_id)->toBe($faseB->id);
    expect($hasil->fresh()->lembaga_id)->toBeNull();
});
