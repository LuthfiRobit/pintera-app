<?php

use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('encrypts npwp_yayasan at rest', function () {
    $yayasan = Yayasan::create([
        'nama' => 'Yayasan Pintera',
        'npwp_yayasan' => '01.234.567.8-901.000',
    ]);

    $raw = \DB::table('yayasan')->where('id', $yayasan->id)->value('npwp_yayasan');

    expect($raw)->not->toBe('01.234.567.8-901.000');
    expect($yayasan->fresh()->npwp_yayasan)->toBe('01.234.567.8-901.000');
});
