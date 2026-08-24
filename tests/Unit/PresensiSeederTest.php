<?php

use App\Domains\Akademik\Models\Presensi;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use Database\Seeders\EssentialUserSeeder;
use Database\Seeders\GuruSeeder;
use Database\Seeders\JadwalPelajaranSeeder;
use Database\Seeders\JamPelajaranSeeder;
use Database\Seeders\KelasSeeder;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\MataPelajaranSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PolaJamSeeder;
use Database\Seeders\PresensiSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SemesterSeeder;
use Database\Seeders\SesiPembelajaranSeeder;
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
    (new JadwalPelajaranSeeder())->run();
    (new SesiPembelajaranSeeder())->run();
});

it('seeds student attendance records for the SD institution, with sakit/izin variation', function () {
    (new PresensiSeeder())->run();

    $sdit = Lembaga::where('npsn', '20223333')->first();
    $aktif = TahunAjaran::where('lembaga_id', $sdit->id)->where('status_aktif', true)->first();
    $kelasIds = Kelas::where('lembaga_id', $sdit->id)->where('tahun_ajaran_id', $aktif->id)->pluck('id');
    $sesiIds = SesiPembelajaran::whereIn('kelas_id', $kelasIds)->pluck('id');

    $presensiCount = Presensi::whereIn('sesi_pembelajaran_id', $sesiIds)->count();
    expect($presensiCount)->toBeGreaterThan(0);

    // Formula PresensiSeeder: index 0 dari tiap kelas (index % 10 === 0) selalu 'sakit'.
    $siswaPertamaKelas1A = Siswa::whereHas('kelas', fn ($q) => $q->where('nama', 'Kelas 1-A'))->orderBy('nis')->first();
    expect(Presensi::where('siswa_id', $siswaPertamaKelas1A->id)->where('status', 'sakit')->exists())->toBeTrue();
});

it('is idempotent when run twice', function () {
    (new PresensiSeeder())->run();
    $sebelum = Presensi::count();
    (new PresensiSeeder())->run();

    expect(Presensi::count())->toBe($sebelum);
});
