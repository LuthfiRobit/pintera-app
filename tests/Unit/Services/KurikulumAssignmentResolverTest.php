<?php

use App\Domains\Akademik\Exceptions\KurikulumAssignmentNotFoundException;
use App\Domains\Akademik\Models\KurikulumAssignment;
use App\Domains\Akademik\Services\KurikulumAssignmentResolver;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('resolves level 1: lembaga exact-match + tingkat exact-match wins over everything else', function () {
    $lembaga = Lembaga::factory()->create();
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);

    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13']);
    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);
    KurikulumAssignment::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13']);
    KurikulumAssignment::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'merdeka']);

    $hasil = app(KurikulumAssignmentResolver::class)->resolve($ta->id, 'SD', '1', $lembaga->id);

    expect($hasil->value)->toBe('merdeka');
});

it('resolves level 2: lembaga exact-match + tingkat catch-all wins over global rows', function () {
    $lembaga = Lembaga::factory()->create();
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);

    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);
    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13']);
    KurikulumAssignment::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'merdeka']);

    $hasil = app(KurikulumAssignmentResolver::class)->resolve($ta->id, 'SD', '1', $lembaga->id);

    expect($hasil->value)->toBe('merdeka');
});

it('resolves level 3: global tingkat exact-match wins over global catch-all', function () {
    $lembaga = Lembaga::factory()->create();
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);

    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13']);
    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'merdeka']);

    $hasil = app(KurikulumAssignmentResolver::class)->resolve($ta->id, 'SD', '1', $lembaga->id);

    expect($hasil->value)->toBe('merdeka');
});

it('resolves level 4: falls back to global catch-all when nothing more specific matches', function () {
    $lembaga = Lembaga::factory()->create();
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);

    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13']);

    $hasil = app(KurikulumAssignmentResolver::class)->resolve($ta->id, 'SD', '3', $lembaga->id);

    expect($hasil->value)->toBe('k13');
});

it('throws KurikulumAssignmentNotFoundException when no assignment matches at all', function () {
    $lembaga = Lembaga::factory()->create();
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);

    $resolve = fn () => app(KurikulumAssignmentResolver::class)->resolve($ta->id, 'SMK', '10', $lembaga->id);

    expect($resolve)->toThrow(KurikulumAssignmentNotFoundException::class);
});

it('does not fall back to an assignment from a different tahun_ajaran', function () {
    $lembaga = Lembaga::factory()->create();
    $taLain = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $taIni = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);

    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $taLain->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13']);

    $resolve = fn () => app(KurikulumAssignmentResolver::class)->resolve($taIni->id, 'SD', '1', $lembaga->id);

    expect($resolve)->toThrow(KurikulumAssignmentNotFoundException::class);
});

it('does not leak another lembaga override into this lembaga resolution', function () {
    $lembagaA = Lembaga::factory()->create();
    $lembagaB = Lembaga::factory()->create();
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);

    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);
    KurikulumAssignment::create(['lembaga_id' => $lembagaB->id, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'merdeka']);

    $hasil = app(KurikulumAssignmentResolver::class)->resolve($ta->id, 'SD', '1', $lembagaA->id);

    expect($hasil->value)->toBe('k13');
});
