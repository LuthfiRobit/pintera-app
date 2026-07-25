<?php

use App\Models\Lembaga;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('defaults hari_libur_mingguan to Sunday and casts it to an array', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    expect($lembaga->fresh()->hari_libur_mingguan)->toBe([0]);
});

it('can store a custom set of weekly off-days, e.g. Friday for a pesantren', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembaga->update(['hari_libur_mingguan' => [5]]);

    expect($lembaga->fresh()->hari_libur_mingguan)->toBe([5]);
});
