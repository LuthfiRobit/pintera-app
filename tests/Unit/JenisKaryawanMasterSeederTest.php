<?php
// tests/Unit/JenisKaryawanMasterSeederTest.php

use App\Models\JenisKaryawanMaster;
use Database\Seeders\JenisKaryawanMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('seeds exactly two konselor-eligible jenis karyawan', function () {
    (new JenisKaryawanMasterSeeder())->run();

    expect(JenisKaryawanMaster::count())->toBe(2);
    expect(JenisKaryawanMaster::where('nama', 'Psikolog')->where('is_konselor', true)->exists())->toBeTrue();
    expect(JenisKaryawanMaster::where('nama', 'Konselor BK')->where('is_konselor', true)->exists())->toBeTrue();
});

it('is idempotent when run twice', function () {
    (new JenisKaryawanMasterSeeder())->run();
    (new JenisKaryawanMasterSeeder())->run();

    expect(JenisKaryawanMaster::count())->toBe(2);
});
