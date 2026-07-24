<?php

use App\Enums\StatusSiswa;
use App\Enums\SumberDataSiswa;
use App\Enums\TipeMataPelajaran;

it('defines the expected SumberDataSiswa cases', function () {
    expect(array_column(SumberDataSiswa::cases(), 'value'))
        ->toBe(['spmb', 'import', 'manual']);
});

it('defines the expected StatusSiswa cases', function () {
    expect(array_column(StatusSiswa::cases(), 'value'))
        ->toBe(['aktif', 'lulus', 'pindah', 'keluar']);
});

it('defines the expected TipeMataPelajaran cases', function () {
    expect(array_column(TipeMataPelajaran::cases(), 'value'))
        ->toBe(['mapel', 'aspek_perkembangan']);
});
