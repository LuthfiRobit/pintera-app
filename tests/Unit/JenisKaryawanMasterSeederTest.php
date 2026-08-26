<?php
// tests/Unit/JenisKaryawanMasterSeederTest.php

use App\Domains\Sdm\Models\JenisKaryawanMaster;
use Database\Seeders\JenisKaryawanMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('seeds exactly two konselor-eligible jenis karyawan and two non-konselor staf umum', function () {
    (new JenisKaryawanMasterSeeder())->run();

    expect(JenisKaryawanMaster::count())->toBe(4);
    expect(JenisKaryawanMaster::where('nama', 'Psikolog')->where('is_konselor', true)->exists())->toBeTrue();
    expect(JenisKaryawanMaster::where('nama', 'Konselor BK')->where('is_konselor', true)->exists())->toBeTrue();
    expect(JenisKaryawanMaster::where('nama', 'Satpam')->where('is_konselor', false)->exists())->toBeTrue();
    expect(JenisKaryawanMaster::where('nama', 'Petugas Kebersihan')->where('is_konselor', false)->exists())->toBeTrue();
});

it('is idempotent when run twice', function () {
    (new JenisKaryawanMasterSeeder())->run();
    (new JenisKaryawanMasterSeeder())->run();

    expect(JenisKaryawanMaster::count())->toBe(4);
});
