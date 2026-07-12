<?php

use App\Models\Lembaga;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('auto-generates a unique slug from nama', function () {
    $yayasan = Yayasan::factory()->create();

    $lembaga = Lembaga::create([
        'yayasan_id' => $yayasan->id,
        'npsn' => '12345678',
        'nama' => 'SD Pintera Satu',
        'bentuk_pendidikan' => 'SD',
        'status_sekolah' => 'swasta',
        'naungan' => 'kemendikdasmen',
    ]);

    expect($lembaga->slug)->toBe('sd-pintera-satu');
});

it('encrypts nomor_rekening and npwp at rest', function () {
    $yayasan = Yayasan::factory()->create();

    $lembaga = Lembaga::create([
        'yayasan_id' => $yayasan->id,
        'npsn' => '87654321',
        'nama' => 'SMP Pintera Dua',
        'bentuk_pendidikan' => 'SMP',
        'status_sekolah' => 'swasta',
        'naungan' => 'kemendikdasmen',
        'nomor_rekening' => '1234567890',
        'npwp' => '02.345.678.9-012.000',
    ]);

    $raw = \DB::table('lembaga')->where('id', $lembaga->id)->first();

    expect($raw->nomor_rekening)->not->toBe('1234567890');
    expect($raw->npwp)->not->toBe('02.345.678.9-012.000');
    expect($lembaga->fresh()->nomor_rekening)->toBe('1234567890');
    expect($lembaga->fresh()->npwp)->toBe('02.345.678.9-012.000');
});
