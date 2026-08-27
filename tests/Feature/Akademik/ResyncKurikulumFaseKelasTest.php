<?php

use App\Domains\Akademik\Actions\Kelas\ResyncKurikulumFaseKelasAction;
use App\Domains\Akademik\Models\Fase;
use App\Domains\Akademik\Models\KurikulumAssignment;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use App\Models\Yayasan;

function siapkanResyncFixture(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'SD']);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);

    return [$lembaga, $ta];
}

it('detects a kelas whose stored kurikulum no longer matches the live assignment', function () {
    [$lembaga, $ta] = siapkanResyncFixture();

    KurikulumAssignment::create([
        'lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13',
    ]);
    $kelas = Kelas::factory()->create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'tingkat' => '1', 'kurikulum' => 'k13',
    ]);

    // Admin mengoreksi assignment SETELAH kelas dibuat -- ini skenario drift-nya.
    KurikulumAssignment::where('tahun_ajaran_id', $ta->id)->first()->update(['kurikulum' => 'merdeka']);

    $action = app(ResyncKurikulumFaseKelasAction::class);
    $diff = $action->hitungDiff($lembaga->id, $ta->id);

    expect($diff)->toHaveCount(1);
    expect($diff[0]['kelas']->id)->toBe($kelas->id);
    expect($diff[0]['kurikulumLama'])->toBe('k13');
    expect($diff[0]['kurikulumBaru'])->toBe('merdeka');
});

it('excludes a kelas whose stored kurikulum already matches the live assignment', function () {
    [$lembaga, $ta] = siapkanResyncFixture();

    KurikulumAssignment::create([
        'lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'merdeka',
    ]);
    Kelas::factory()->create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'tingkat' => '1', 'kurikulum' => 'merdeka',
    ]);

    $action = app(ResyncKurikulumFaseKelasAction::class);
    $diff = $action->hitungDiff($lembaga->id, $ta->id);

    expect($diff)->toBeEmpty();
});

it('excludes a kelas whose assignment cannot be resolved at all', function () {
    [$lembaga, $ta] = siapkanResyncFixture();
    // TIDAK ADA KurikulumAssignment sama sekali untuk kombinasi ini.
    Kelas::factory()->create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'tingkat' => '1', 'kurikulum' => 'k13',
    ]);

    $action = app(ResyncKurikulumFaseKelasAction::class);
    $diff = $action->hitungDiff($lembaga->id, $ta->id);

    expect($diff)->toBeEmpty();
});

it('does not include kelas from a different lembaga in the diff', function () {
    [$lembaga, $ta] = siapkanResyncFixture();
    [$lembagaLain, $taLain] = siapkanResyncFixture();

    KurikulumAssignment::create([
        'lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13',
    ]);
    KurikulumAssignment::create([
        'lembaga_id' => null, 'tahun_ajaran_id' => $taLain->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13',
    ]);
    Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'tingkat' => '1', 'kurikulum' => 'k13']);
    $kelasLain = Kelas::factory()->create(['lembaga_id' => $lembagaLain->id, 'tahun_ajaran_id' => $taLain->id, 'tingkat' => '1', 'kurikulum' => 'k13']);

    KurikulumAssignment::where('tahun_ajaran_id', $taLain->id)->first()->update(['kurikulum' => 'merdeka']);

    $action = app(ResyncKurikulumFaseKelasAction::class);
    $diff = $action->hitungDiff($lembaga->id, $ta->id);

    expect($diff)->toBeEmpty();
    expect(Kelas::find($kelasLain->id)->kurikulum->value)->toBe('k13'); // belum di-resync, cuma bukti data lembaga lain tak tersentuh
});

it('applies resync only to the selected kelas ids, recomputing values on the server', function () {
    [$lembaga, $ta] = siapkanResyncFixture();
    $fase = Fase::firstOrCreate(['kode' => 'a'], ['nama' => 'Fase A', 'urutan' => 1]);

    KurikulumAssignment::create([
        'lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13',
    ]);
    $kelasA = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'tingkat' => '1', 'kurikulum' => 'k13', 'fase_id' => null]);
    $kelasB = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'tingkat' => '2', 'kurikulum' => 'k13', 'fase_id' => null]);

    KurikulumAssignment::where('tahun_ajaran_id', $ta->id)->first()->update(['kurikulum' => 'merdeka']);

    $action = app(ResyncKurikulumFaseKelasAction::class);
    $action->terapkan([$kelasA->id]);

    expect($kelasA->fresh()->kurikulum->value)->toBe('merdeka');
    expect($kelasB->fresh()->kurikulum->value)->toBe('k13'); // tidak dicentang, tidak berubah
});
