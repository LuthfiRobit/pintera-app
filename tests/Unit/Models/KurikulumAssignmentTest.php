<?php

use App\Domains\Akademik\Enums\KurikulumFramework;
use App\Domains\Akademik\Models\KurikulumAssignment;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('casts kurikulum column to KurikulumFramework enum', function () {
    $lembaga = Lembaga::factory()->create();
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);

    $assignment = KurikulumAssignment::create([
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $ta->id,
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'kurikulum' => 'merdeka',
    ]);

    expect($assignment->fresh()->kurikulum)->toBe(KurikulumFramework::Merdeka);
});

it('allows a global default assignment with lembaga_id null', function () {
    $ta = TahunAjaran::factory()->create();

    $assignment = KurikulumAssignment::create([
        'lembaga_id' => null,
        'tahun_ajaran_id' => $ta->id,
        'bentuk_pendidikan' => 'SD',
        'tingkat' => null,
        'kurikulum' => 'k13',
    ]);

    expect($assignment->fresh()->lembaga_id)->toBeNull();
});

it('rejects a duplicate assignment for the identical scope (lembaga+tahun_ajaran+bentuk+tingkat)', function () {
    $lembaga = Lembaga::factory()->create();
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);

    KurikulumAssignment::create([
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $ta->id,
        'bentuk_pendidikan' => 'SD',
        'tingkat' => null,
        'kurikulum' => 'k13',
    ]);

    $duplikat = fn () => KurikulumAssignment::create([
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $ta->id,
        'bentuk_pendidikan' => 'SD',
        'tingkat' => null,
        'kurikulum' => 'merdeka',
    ]);

    expect($duplikat)->toThrow(QueryException::class);
});

it('allows two global-default assignments for different tahun_ajaran (tahun_ajaran_id is part of the unique key)', function () {
    $taA = TahunAjaran::factory()->create();
    $taB = TahunAjaran::factory()->create();

    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $taA->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13']);
    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $taB->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'merdeka']);

    expect(KurikulumAssignment::count())->toBe(2);
});
