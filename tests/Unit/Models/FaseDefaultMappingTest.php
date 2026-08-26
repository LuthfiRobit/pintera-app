<?php

use App\Domains\Akademik\Models\Fase;
use App\Domains\Akademik\Models\FaseDefaultMapping;
use App\Models\Lembaga;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function buatFase(): Fase
{
    return Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
}

it('allows creating a platform-wide mapping (lembaga_id null) and a lembaga-specific mapping for the same bentuk_pendidikan+tingkat', function () {
    $fase = buatFase();
    $lembaga = Lembaga::factory()->create();

    FaseDefaultMapping::create(['lembaga_id' => null, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $fase->id]);
    $lembagaSpesifik = FaseDefaultMapping::create(['lembaga_id' => $lembaga->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $fase->id]);

    expect($lembagaSpesifik->fresh()->lembaga_id)->toBe($lembaga->id);
});

it('rejects two platform-wide mappings with identical bentuk_pendidikan and tingkat', function () {
    $fase = buatFase();
    FaseDefaultMapping::create(['lembaga_id' => null, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $fase->id]);

    expect(fn () => FaseDefaultMapping::create(['lembaga_id' => null, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $fase->id]))
        ->toThrow(Illuminate\Database\QueryException::class);
});

it('rejects two platform-wide catch-all mappings (tingkat null) for the same bentuk_pendidikan', function () {
    $fase = buatFase();
    FaseDefaultMapping::create(['lembaga_id' => null, 'bentuk_pendidikan' => 'SMP', 'tingkat' => null, 'fase_id' => $fase->id]);

    expect(fn () => FaseDefaultMapping::create(['lembaga_id' => null, 'bentuk_pendidikan' => 'SMP', 'tingkat' => null, 'fase_id' => $fase->id]))
        ->toThrow(Illuminate\Database\QueryException::class);
});

it('rejects two lembaga-specific mappings with identical scope for the same lembaga', function () {
    $fase = buatFase();
    $lembaga = Lembaga::factory()->create();
    FaseDefaultMapping::create(['lembaga_id' => $lembaga->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $fase->id]);

    expect(fn () => FaseDefaultMapping::create(['lembaga_id' => $lembaga->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $fase->id]))
        ->toThrow(Illuminate\Database\QueryException::class);
});

it('allows the same bentuk_pendidikan+tingkat scope for two different lembaga', function () {
    $fase = buatFase();
    $lembagaA = Lembaga::factory()->create();
    $lembagaB = Lembaga::factory()->create();

    FaseDefaultMapping::create(['lembaga_id' => $lembagaA->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $fase->id]);
    $keduaLembaga = FaseDefaultMapping::create(['lembaga_id' => $lembagaB->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $fase->id]);

    expect($keduaLembaga->fresh()->id)->not->toBeNull();
});
