<?php

use App\Domains\Akademik\Enums\StatusPresensi;
use App\Domains\Akademik\Enums\StatusSesiPembelajaran;

it('defines the expected StatusSesiPembelajaran cases', function () {
    expect(array_column(StatusSesiPembelajaran::cases(), 'value'))
        ->toBe(['terlaksana', 'diganti', 'kosong']);
});

it('defines the expected StatusPresensi cases', function () {
    expect(array_column(StatusPresensi::cases(), 'value'))
        ->toBe(['hadir', 'izin', 'sakit', 'alpa', 'terlambat']);
});
