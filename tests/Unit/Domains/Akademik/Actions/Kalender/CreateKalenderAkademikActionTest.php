<?php

use App\Domains\Akademik\Actions\Kalender\CreateKalenderAkademikAction;
use App\Domains\Akademik\DataTransferObjects\KalenderAkademikData;
use App\Domains\Akademik\Models\KalenderAkademik;
use App\Models\Lembaga;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('creates a lembaga-scoped kalender entry', function () {
    $lembaga = Lembaga::factory()->create();

    $entri = (new CreateKalenderAkademikAction)->execute(
        new KalenderAkademikData(
            tanggal: '2026-09-01',
            tanggalSelesai: null,
            nama: 'Libur Semester',
            tipe: 'libur',
            keterangan: null,
            berlakuNasional: false,
        ),
        $lembaga->id
    );

    expect($entri->lembaga_id)->toBe($lembaga->id)
        ->and($entri->nama)->toBe('Libur Semester');
});

it('rejects a date range that overlaps an existing entry in the same scope', function () {
    $lembaga = Lembaga::factory()->create();
    KalenderAkademik::factory()->create([
        'lembaga_id' => $lembaga->id,
        'tanggal' => '2026-09-01',
        'tanggal_selesai' => '2026-09-05',
    ]);

    expect(fn () => (new CreateKalenderAkademikAction)->execute(
        new KalenderAkademikData(
            tanggal: '2026-09-03',
            tanggalSelesai: '2026-09-10',
            nama: 'Entri Baru',
            tipe: 'libur',
            keterangan: null,
            berlakuNasional: false,
        ),
        $lembaga->id
    ))->toThrow(ValidationException::class);
});

it('does not flag overlap against a different lembaga scope', function () {
    $lembagaA = Lembaga::factory()->create();
    $lembagaB = Lembaga::factory()->create();
    KalenderAkademik::factory()->create([
        'lembaga_id' => $lembagaA->id,
        'tanggal' => '2026-09-01',
        'tanggal_selesai' => '2026-09-05',
    ]);

    $entri = (new CreateKalenderAkademikAction)->execute(
        new KalenderAkademikData(
            tanggal: '2026-09-03',
            tanggalSelesai: '2026-09-05',
            nama: 'Entri Lembaga Lain',
            tipe: 'libur',
            keterangan: null,
            berlakuNasional: false,
        ),
        $lembagaB->id
    );

    expect($entri->lembaga_id)->toBe($lembagaB->id);
});
