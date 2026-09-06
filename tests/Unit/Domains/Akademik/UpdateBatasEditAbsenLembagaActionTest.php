<?php

use App\Domains\Akademik\Actions\Kalender\UpdateBatasEditAbsenLembagaAction;
use App\Domains\Akademik\DataTransferObjects\BatasEditAbsenLembagaData;
use App\Models\Lembaga;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('mengubah batas_edit_absen_hari lembaga sesuai DTO', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'batas_edit_absen_hari' => 3]);

    $hasil = (new UpdateBatasEditAbsenLembagaAction)->execute($lembaga, new BatasEditAbsenLembagaData(batasHari: 7));

    expect($hasil->batas_edit_absen_hari)->toBe(7);
    expect($lembaga->fresh()->batas_edit_absen_hari)->toBe(7);
});
