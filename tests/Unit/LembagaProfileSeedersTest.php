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
use Database\Seeders\YayasanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new YayasanSeeder())->run();
    (new LembagaSeeder())->run();
});

it('seeds Kelas Tahfidz Intensif as a layanan khusus for all lembaga', function () {
    (new LayananKhususLembagaSeeder())->run();

    foreach (Lembaga::all() as $lembaga) {
        expect(LayananKhususLembaga::where('lembaga_id', $lembaga->id)->where('jenis_layanan', 'Kelas Tahfidz Intensif')->exists())->toBeTrue();
    }
});

it('seeds Tunadaksa as a program inklusi for all lembaga', function () {
    (new ProgramInklusiLembagaSeeder())->run();

    foreach (Lembaga::all() as $lembaga) {
        expect(ProgramInklusiLembaga::where('lembaga_id', $lembaga->id)->where('kebutuhan_khusus', 'Tunadaksa')->exists())->toBeTrue();
    }
});

it('seeds jenjang-specific ekstrakurikuler across K-9 institutions', function () {
    (new EkstrakurikulerLembagaSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    expect(EkstrakurikulerLembaga::where('lembaga_id', $smp->id)->where('nama_ekskul', 'Futsal')->exists())->toBeTrue();

    $sd = Lembaga::where('npsn', '20223333')->first();
    expect(EkstrakurikulerLembaga::where('lembaga_id', $sd->id)->where('nama_ekskul', 'Pramuka')->exists())->toBeTrue();
});
