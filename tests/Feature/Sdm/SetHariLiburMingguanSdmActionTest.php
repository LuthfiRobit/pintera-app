<?php
// tests/Feature/Sdm/SetHariLiburMingguanSdmActionTest.php

use App\Domains\Sdm\Actions\SetHariLiburMingguanSdmAction;
use App\Domains\Sdm\DataTransferObjects\HariKerjaSdmData;
use App\Models\Lembaga;
use App\Models\Yayasan;

it('converts a positive work-day list into the stored negative off-day list', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $updated = app(SetHariLiburMingguanSdmAction::class)->execute($lembaga, new HariKerjaSdmData(hariKerja: [1, 2, 3, 4, 5]));

    expect($updated->hari_libur_mingguan_sdm)->toBe([0, 6]);
});

it('supports a 6-day work week (only Sunday off)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $updated = app(SetHariLiburMingguanSdmAction::class)->execute($lembaga, new HariKerjaSdmData(hariKerja: [1, 2, 3, 4, 5, 6]));

    expect($updated->hari_libur_mingguan_sdm)->toBe([0]);
});
