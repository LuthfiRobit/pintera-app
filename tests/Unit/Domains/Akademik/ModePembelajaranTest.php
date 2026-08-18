<?php

use App\Domains\Akademik\Enums\ModePembelajaran;

it('maps SMP, SMA, and SMK to SesiMapel', function (string $bentukPendidikan) {
    expect(ModePembelajaran::fromBentukPendidikan($bentukPendidikan))->toBe(ModePembelajaran::SesiMapel);
})->with(['SMP', 'SMA', 'SMK']);

it('maps every other bentuk_pendidikan value to Tematik', function (string $bentukPendidikan) {
    expect(ModePembelajaran::fromBentukPendidikan($bentukPendidikan))->toBe(ModePembelajaran::Tematik);
})->with(['KB', 'TPA', 'SPS', 'TK', 'SD', 'SLB']);

it('defaults an unrecognized future bentuk_pendidikan value to Tematik, not SesiMapel', function () {
    expect(ModePembelajaran::fromBentukPendidikan('JENJANG_BARU_YANG_BELUM_ADA'))->toBe(ModePembelajaran::Tematik);
});
