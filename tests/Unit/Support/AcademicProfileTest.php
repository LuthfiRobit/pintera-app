<?php

use App\Domains\Akademik\Enums\ModePembelajaran;
use App\Domains\Akademik\Support\AcademicProfile;

it('derives the correct learningMode and reportTemplate for every known bentuk_pendidikan', function (string $bentukPendidikan, string $expectedMode, string $expectedTemplate) {
    $profile = AcademicProfile::fromBentukPendidikan($bentukPendidikan);

    expect($profile->learningMode->name)->toBe($expectedMode);
    expect($profile->reportTemplate)->toBe($expectedTemplate);
})->with([
    ['KB', 'Tematik', 'paud'],
    ['TPA', 'Tematik', 'paud'],
    ['SPS', 'Tematik', 'paud'],
    ['TK', 'Tematik', 'paud'],
    ['SD', 'Tematik', 'sd'],
    ['SMP', 'SesiMapel', 'smp-sma'],
    ['SMA', 'SesiMapel', 'smp-sma'],
    ['SMK', 'SesiMapel', 'smk'],
    ['SLB', 'Tematik', 'sd'],
]);

it('keeps learningMode identical to calling ModePembelajaran::fromBentukPendidikan() directly, for every known bentuk_pendidikan', function (string $bentukPendidikan) {
    $profile = AcademicProfile::fromBentukPendidikan($bentukPendidikan);

    expect($profile->learningMode)->toBe(ModePembelajaran::fromBentukPendidikan($bentukPendidikan));
})->with(['KB', 'TPA', 'SPS', 'TK', 'SD', 'SMP', 'SMA', 'SMK', 'SLB']);

it('throws for an unknown bentuk_pendidikan instead of silently falling back', function () {
    expect(fn () => AcademicProfile::fromBentukPendidikan('XYZ'))
        ->toThrow(InvalidArgumentException::class, 'Unsupported bentuk_pendidikan: XYZ');
});

it('throws for an empty string bentuk_pendidikan', function () {
    expect(fn () => AcademicProfile::fromBentukPendidikan(''))
        ->toThrow(InvalidArgumentException::class);
});
