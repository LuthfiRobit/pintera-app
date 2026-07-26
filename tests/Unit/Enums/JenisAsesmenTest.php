<?php

use App\Enums\JenisAsesmen;

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

it('exposes only the 3 sumatif cases as v1-supported', function () {
    expect(JenisAsesmen::v1Didukung())->toBe([
        JenisAsesmen::SumatifLingkupMateri,
        JenisAsesmen::SumatifAkhirSemester,
        JenisAsesmen::SumatifAkhirJenjang,
    ]);
});

it('returns correct Indonesian labels for all cases', function () {
    expect(JenisAsesmen::SumatifLingkupMateri->label())->toBe('Sumatif Lingkup Materi');
    expect(JenisAsesmen::SumatifAkhirSemester->label())->toBe('Sumatif Akhir Semester');
    expect(JenisAsesmen::SumatifAkhirJenjang->label())->toBe('Sumatif Akhir Jenjang');
});
