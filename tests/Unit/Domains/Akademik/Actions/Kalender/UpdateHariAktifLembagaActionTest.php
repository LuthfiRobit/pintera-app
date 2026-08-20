<?php

use App\Domains\Akademik\Actions\Kalender\UpdateHariAktifLembagaAction;
use App\Domains\Akademik\DataTransferObjects\HariAktifLembagaData;
use App\Models\Lembaga;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('sets hari_libur_mingguan as the complement of the active days provided', function () {
    $lembaga = Lembaga::factory()->create();

    $result = (new UpdateHariAktifLembagaAction)->execute(
        $lembaga,
        new HariAktifLembagaData(hariAktif: [1, 2, 3, 4, 5])
    );

    expect($result->hari_libur_mingguan)->toEqualCanonicalizing([0, 6]);
});

it('marks every day as libur when no active day is provided', function () {
    $lembaga = Lembaga::factory()->create();

    $result = (new UpdateHariAktifLembagaAction)->execute(
        $lembaga,
        new HariAktifLembagaData(hariAktif: [])
    );

    expect($result->hari_libur_mingguan)->toEqualCanonicalizing([0, 1, 2, 3, 4, 5, 6]);
});
