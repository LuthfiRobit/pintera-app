<?php

use App\Domains\Akademik\Models\Fase;
use App\Domains\Akademik\Models\FaseDefaultMapping;
use App\Domains\Akademik\Services\FaseDefaultResolver;
use App\Models\Lembaga;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function buatFaseResolverTest(string $kode, int $urutan): Fase
{
    return Fase::create(['kode' => $kode, 'nama' => "Fase {$kode}", 'urutan' => $urutan]);
}

it('resolves platform exact-match mapping when no lembaga override exists', function () {
    $faseA = buatFaseResolverTest('a', 1);
    FaseDefaultMapping::create(['lembaga_id' => null, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $faseA->id]);
    $lembaga = Lembaga::factory()->create();

    $hasil = app(FaseDefaultResolver::class)->resolve('SD', '1', $lembaga->id);

    expect($hasil?->kode)->toBe('a');
});

it('lembaga exact-match override wins over platform exact-match', function () {
    $faseA = buatFaseResolverTest('a', 1);
    $faseB = buatFaseResolverTest('b', 2);
    $lembaga = Lembaga::factory()->create();

    FaseDefaultMapping::create(['lembaga_id' => null, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $faseA->id]);
    FaseDefaultMapping::create(['lembaga_id' => $lembaga->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $faseB->id]);

    $hasil = app(FaseDefaultResolver::class)->resolve('SD', '1', $lembaga->id);

    expect($hasil?->kode)->toBe('b');
});

it('lembaga catch-all wins over platform exact-match (level 2 beats level 3 in precedence)', function () {
    $faseA = buatFaseResolverTest('a', 1);
    $faseFondasi = buatFaseResolverTest('foundation', 0);
    $lembaga = Lembaga::factory()->create();

    FaseDefaultMapping::create(['lembaga_id' => null, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $faseA->id]);
    FaseDefaultMapping::create(['lembaga_id' => $lembaga->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'fase_id' => $faseFondasi->id]);

    $hasil = app(FaseDefaultResolver::class)->resolve('SD', '1', $lembaga->id);

    expect($hasil?->kode)->toBe('foundation');
});

it('falls back to platform catch-all when nothing more specific matches', function () {
    $faseD = buatFaseResolverTest('d', 4);
    $lembaga = Lembaga::factory()->create();
    FaseDefaultMapping::create(['lembaga_id' => null, 'bentuk_pendidikan' => 'SMP', 'tingkat' => null, 'fase_id' => $faseD->id]);

    $hasil = app(FaseDefaultResolver::class)->resolve('SMP', '7', $lembaga->id);

    expect($hasil?->kode)->toBe('d');
});

it('returns null when no mapping matches at all', function () {
    $lembaga = Lembaga::factory()->create();

    $hasil = app(FaseDefaultResolver::class)->resolve('SLB', '6', $lembaga->id);

    expect($hasil)->toBeNull();
});

it('does not leak another lembaga override into this lembaga resolution', function () {
    $faseA = buatFaseResolverTest('a', 1);
    $faseB = buatFaseResolverTest('b', 2);
    $lembagaA = Lembaga::factory()->create();
    $lembagaB = Lembaga::factory()->create();

    FaseDefaultMapping::create(['lembaga_id' => null, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $faseA->id]);
    FaseDefaultMapping::create(['lembaga_id' => $lembagaB->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $faseB->id]);

    $hasil = app(FaseDefaultResolver::class)->resolve('SD', '1', $lembagaA->id);

    expect($hasil?->kode)->toBe('a'); // lembagaA tidak terpengaruh override milik lembagaB
});
