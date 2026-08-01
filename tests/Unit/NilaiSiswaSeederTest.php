<?php

use App\Models\Asesmen;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\NilaiSiswa;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use Database\Seeders\AsesmenSeeder;
use Database\Seeders\EssentialUserSeeder;
use Database\Seeders\GuruSeeder;
use Database\Seeders\JamPelajaranSeeder;
use Database\Seeders\KelasSeeder;
use Database\Seeders\KomponenPenilaianSeeder;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\MataPelajaranSeeder;
use Database\Seeders\NilaiSiswaSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PolaJamSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SemesterSeeder;
use Database\Seeders\SiswaSeeder;
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
    (new SiswaSeeder())->run();
    (new KomponenPenilaianSeeder())->run();
    (new AsesmenSeeder())->run();
});

it('seeds student grades across all K-9 institutions', function () {
    (new NilaiSiswaSeeder())->run();

    foreach (Lembaga::all() as $lembaga) {
        $aktif = TahunAjaran::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first();
        $kelasIds = Kelas::where('lembaga_id', $lembaga->id)->where('tahun_ajaran_id', $aktif->id)->pluck('id');
        $asesmenIds = Asesmen::whereIn('kelas_id', $kelasIds)->pluck('id');

        $nilaiCount = NilaiSiswa::whereIn('asesmen_id', $asesmenIds)->count();
        expect($nilaiCount)->toBeGreaterThan(0);
    }

    $smp = Lembaga::where('npsn', '20223344')->first();
    $aditya = Siswa::where('lembaga_id', $smp->id)->where('nis', '2627001')->first();
    expect(NilaiSiswa::where('siswa_id', $aditya->id)->count())->toBeGreaterThan(0);
});

it('is idempotent when run twice', function () {
    (new NilaiSiswaSeeder())->run();
    $sebelum = NilaiSiswa::count();
    (new NilaiSiswaSeeder())->run();

    expect(NilaiSiswa::count())->toBe($sebelum);
});
