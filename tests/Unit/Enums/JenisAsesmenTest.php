<?php

use App\Domains\Akademik\Enums\JenisAsesmen;

it('defines all 6 cases from the design spec', function () {
    expect(array_column(JenisAsesmen::cases(), 'value'))->toBe([
        'diagnostik_kognitif',
        'diagnostik_non_kognitif',
        'formatif',
        'sumatif_lingkup_materi',
        'sumatif_akhir_semester',
        'sumatif_akhir_jenjang',
    ]);
});

it('exposes exactly the 3 sumatif cases as sources of rapor calculation', function () {
    expect(JenisAsesmen::masukRapor())->toBe([
        JenisAsesmen::SumatifLingkupMateri,
        JenisAsesmen::SumatifAkhirSemester,
        JenisAsesmen::SumatifAkhirJenjang,
    ]);
});

it('no longer has the retired v1Didukung() method', function () {
    expect(method_exists(JenisAsesmen::class, 'v1Didukung'))->toBeFalse();
});

it('returns correct Indonesian labels for all 6 cases', function () {
    expect(JenisAsesmen::DiagnostikKognitif->label())->toBe('Diagnostik Kognitif');
    expect(JenisAsesmen::DiagnostikNonKognitif->label())->toBe('Diagnostik Non-Kognitif');
    expect(JenisAsesmen::Formatif->label())->toBe('Formatif');
    expect(JenisAsesmen::SumatifLingkupMateri->label())->toBe('Sumatif Lingkup Materi');
    expect(JenisAsesmen::SumatifAkhirSemester->label())->toBe('Sumatif Akhir Semester');
    expect(JenisAsesmen::SumatifAkhirJenjang->label())->toBe('Sumatif Akhir Jenjang');
});
