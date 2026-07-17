<?php

use App\Models\Lembaga;
use App\Models\LembagaDataPeriodik;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use Database\Seeders\LembagaDataPeriodikSeeder;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\SemesterSeeder;
use Database\Seeders\TahunAjaranSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Yayasan::factory()->create();
    (new LembagaSeeder())->run();
    (new TahunAjaranSeeder())->run();
    (new SemesterSeeder())->run();
});

it('seeds a data periodik row for each semester of the active tahun ajaran, per lembaga', function () {
    (new LembagaDataPeriodikSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    $aktif = TahunAjaran::where('lembaga_id', $smp->id)->where('status_aktif', true)->first();
    $semesterAktif = Semester::where('tahun_ajaran_id', $aktif->id)->get();

    foreach ($semesterAktif as $semester) {
        $periodik = LembagaDataPeriodik::where('lembaga_id', $smp->id)->where('semester_id', $semester->id)->first();
        expect($periodik)->not->toBeNull();
        expect($periodik->sumber_listrik)->toBe('PLN');
        expect($periodik->daya_listrik)->toBe(5500);
    }
});

it('is idempotent when run twice', function () {
    (new LembagaDataPeriodikSeeder())->run();
    $sebelum = LembagaDataPeriodik::count();
    (new LembagaDataPeriodikSeeder())->run();

    expect(LembagaDataPeriodik::count())->toBe($sebelum);
});
