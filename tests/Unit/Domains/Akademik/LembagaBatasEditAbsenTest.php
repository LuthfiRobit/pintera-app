<?php

use App\Models\Lembaga;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('lembaga baru otomatis dapat batas_edit_absen_hari default 3 tanpa disebut eksplisit', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    expect($lembaga->fresh()->batas_edit_absen_hari)->toBe(3);
});

it('batas_edit_absen_hari bisa diisi manual lewat mass assignment', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembaga->update(['batas_edit_absen_hari' => 7]);

    expect($lembaga->fresh()->batas_edit_absen_hari)->toBe(7);
});
