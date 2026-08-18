<?php

use App\Domains\Akademik\Models\SesiPembelajaran;
use Tests\TestCase;

uses(TestCase::class);

it('reports isTematik true when jadwal_pelajaran_id is null', function () {
    $sesi = new SesiPembelajaran(['jadwal_pelajaran_id' => null]);

    expect($sesi->isTematik())->toBeTrue();
});

it('reports isTematik false when jadwal_pelajaran_id is set', function () {
    $sesi = new SesiPembelajaran(['jadwal_pelajaran_id' => 42]);

    expect($sesi->isTematik())->toBeFalse();
});
