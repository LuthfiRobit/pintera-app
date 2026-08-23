<?php

use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Models\Lembaga;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use Database\Seeders\EssentialUserSeeder;
use Database\Seeders\GuruSeeder;
use Database\Seeders\JamPelajaranSeeder;
use Database\Seeders\KelasSeeder;
use Database\Seeders\KomponenPenilaianSeeder;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\MataPelajaranSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PolaJamSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SemesterSeeder;
use Database\Seeders\TahunAjaranSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new PermissionSeeder())->run();
    (new RoleSeeder())->run();
    Yayasan::factory()->create();
    (new LembagaSeeder())->run();
    (new EssentialUserSeeder())->run();
    (new UserSeeder())->run();
    (new TahunAjaranSeeder())->run();
    (new SemesterSeeder())->run();
    (new GuruSeeder())->run();
    (new PolaJamSeeder())->run();
    (new JamPelajaranSeeder())->run();
    (new MataPelajaranSeeder())->run();
    (new KelasSeeder())->run();
});

it('seeds assessment components across all K-9 institutions', function () {
    (new KomponenPenilaianSeeder())->run();

    foreach (Lembaga::all() as $lembaga) {
        $mapelIds = MataPelajaran::where('lembaga_id', $lembaga->id)->pluck('id');
        $komponenCount = KomponenPenilaian::whereIn('mata_pelajaran_id', $mapelIds)->count();

        expect($komponenCount)->toBeGreaterThan(0);
    }

    $smp = Lembaga::where('npsn', '20223344')->first();
    $mtk = MataPelajaran::where('lembaga_id', $smp->id)->where('nama', 'Matematika')->first();
    expect(KomponenPenilaian::where('mata_pelajaran_id', $mtk->id)->where('kode', 'TP.1.1')->where('bobot', 50)->exists())->toBeTrue();
    expect((int) KomponenPenilaian::where('mata_pelajaran_id', $mtk->id)->sum('bobot'))->toBe(100);
});

it('is idempotent when run twice', function () {
    (new KomponenPenilaianSeeder())->run();
    $sebelum = KomponenPenilaian::count();
    (new KomponenPenilaianSeeder())->run();

    expect(KomponenPenilaian::count())->toBe($sebelum);
});
