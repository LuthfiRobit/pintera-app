<?php

use App\Models\EkstrakurikulerLembaga;
use App\Models\Lembaga;
use App\Models\LayananKhususLembaga;
use App\Models\ProgramInklusiLembaga;
use App\Models\Yayasan;
use Database\Seeders\EkstrakurikulerLembagaSeeder;
use Database\Seeders\LayananKhususLembagaSeeder;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\ProgramInklusiLembagaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Yayasan::factory()->create();
    (new LembagaSeeder())->run();
});

it('seeds Kelas Tahfidz Intensif as a layanan khusus for both lembaga', function () {
    (new LayananKhususLembagaSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    expect(LayananKhususLembaga::where('lembaga_id', $smp->id)->where('jenis_layanan', 'Kelas Tahfidz Intensif')->exists())->toBeTrue();
});

it('seeds Tunadaksa as a program inklusi for both lembaga', function () {
    (new ProgramInklusiLembagaSeeder())->run();

    $sma = Lembaga::where('npsn', '20223355')->first();
    expect(ProgramInklusiLembaga::where('lembaga_id', $sma->id)->where('kebutuhan_khusus', 'Tunadaksa')->exists())->toBeTrue();
});

it('seeds jenjang-specific ekstrakurikuler for SMP and SMA', function () {
    (new EkstrakurikulerLembagaSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    expect(EkstrakurikulerLembaga::where('lembaga_id', $smp->id)->where('nama_ekskul', 'Futsal')->exists())->toBeTrue();

    $sma = Lembaga::where('npsn', '20223355')->first();
    expect(EkstrakurikulerLembaga::where('lembaga_id', $sma->id)->where('nama_ekskul', 'Basket')->exists())->toBeTrue();
});
