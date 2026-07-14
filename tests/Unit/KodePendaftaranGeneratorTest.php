<?php

use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Services\KodePendaftaranGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('generates a code in the REG-{tahun}-{5 digit} format starting from 00001', function () {
    $lembaga = Lembaga::factory()->create();

    $kode = (new KodePendaftaranGenerator())->generate($lembaga->id);

    expect($kode)->toBe('REG-'.now()->year.'-00001');
});

it('increments the sequence per lembaga per year based on existing pendaftaran', function () {
    $lembaga = Lembaga::factory()->create();
    Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'kode_pendaftaran' => 'REG-'.now()->year.'-00001']);

    $kode = (new KodePendaftaranGenerator())->generate($lembaga->id);

    expect($kode)->toBe('REG-'.now()->year.'-00002');
});

it('keeps sequences independent between different lembaga', function () {
    $lembagaA = Lembaga::factory()->create();
    $lembagaB = Lembaga::factory()->create();
    Pendaftaran::factory()->create(['lembaga_id' => $lembagaA->id, 'kode_pendaftaran' => 'REG-'.now()->year.'-00001']);

    $kode = (new KodePendaftaranGenerator())->generate($lembagaB->id);

    expect($kode)->toBe('REG-'.now()->year.'-00001');
});

it('skips a candidate code that already exists (defensive collision handling)', function () {
    $lembaga = Lembaga::factory()->create();
    Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'kode_pendaftaran' => 'REG-'.now()->year.'-00001']);
    // Simulate a gap where the "next" count-based guess collides with a code
    // that already exists for a different reason (e.g. manually seeded data).
    Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'kode_pendaftaran' => 'REG-'.now()->year.'-00002']);

    $kode = (new KodePendaftaranGenerator())->generate($lembaga->id);

    expect($kode)->toBe('REG-'.now()->year.'-00003');
});
