<?php

use App\Domains\Akademik\Actions\Kelas\UpdateKelasAction;
use App\Domains\Akademik\DataTransferObjects\KelasData;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('updates kelas fields', function () {
    $lembaga = Lembaga::factory()->create();
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Kelas Lama']);

    $hasil = app(UpdateKelasAction::class)->execute($kelas, new KelasData(
        tahunAjaranId: $tahunAjaran->id,
        nama: 'Kelas Baru',
        tingkat: '2',
        faseId: null,
        waliKelasGuruId: null,
        polaJamId: null,
    ));

    expect($hasil->fresh()->nama)->toBe('Kelas Baru');
    expect($hasil->fresh()->tingkat)->toBe('2');
});

it('aborts with 404 when tahun_ajaran belongs to a different lembaga than the kelas', function () {
    $lembagaA = Lembaga::factory()->create();
    $lembagaB = Lembaga::factory()->create();
    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembagaA->id]);

    $execute = fn () => app(UpdateKelasAction::class)->execute($kelas, new KelasData(
        tahunAjaranId: $tahunAjaranLain->id,
        nama: $kelas->nama,
        tingkat: $kelas->tingkat,
        faseId: null,
        waliKelasGuruId: null,
        polaJamId: null,
    ));

    expect($execute)->toThrow(NotFoundHttpException::class);
});
