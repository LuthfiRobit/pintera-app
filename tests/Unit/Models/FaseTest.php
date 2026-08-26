<?php

use App\Domains\Akademik\Models\Fase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('stores kode, nama, and urutan for a fase', function () {
    $fase = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);

    expect($fase->fresh()->kode)->toBe('a');
    expect($fase->fresh()->nama)->toBe('Fase A');
    expect($fase->fresh()->urutan)->toBe(1);
});

it('enforces unique kode', function () {
    Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);

    expect(fn () => Fase::create(['kode' => 'a', 'nama' => 'Fase A Duplikat', 'urutan' => 2]))
        ->toThrow(Illuminate\Database\QueryException::class);
});
