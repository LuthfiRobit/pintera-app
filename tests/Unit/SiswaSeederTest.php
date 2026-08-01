<?php

use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use Database\Seeders\EssentialUserSeeder;
use Database\Seeders\GuruSeeder;
use Database\Seeders\KelasSeeder;
use Database\Seeders\LembagaSeeder;
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
    (new KelasSeeder())->run();
});

it('seeds students into active classes across all K-9 institutions', function () {
    (new SiswaSeeder())->run();

    foreach (Lembaga::all() as $lembaga) {
        $aktif = TahunAjaran::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first();
        $kelasIds = Kelas::where('lembaga_id', $lembaga->id)->where('tahun_ajaran_id', $aktif->id)->pluck('id');
        
        $siswaCount = Siswa::whereIn('kelas_id', $kelasIds)->count();
        expect($siswaCount)->toBeGreaterThan(0);

        $siswaWithUser = Siswa::whereIn('kelas_id', $kelasIds)->whereNotNull('user_id')->first();
        expect($siswaWithUser)->not->toBeNull();
        expect($siswaWithUser->user->hasRole('siswa'))->toBeTrue();
    }

    $smp = Lembaga::where('npsn', '20223344')->first();
    $aditya = Siswa::where('lembaga_id', $smp->id)->where('nis', '2627001')->first();
    expect($aditya)->not->toBeNull();
    expect($aditya->nama_lengkap)->toBe('Aditya Pratama');
});

it('is idempotent when run twice', function () {
    (new SiswaSeeder())->run();
    $sebelum = Siswa::count();
    (new SiswaSeeder())->run();

    expect(Siswa::count())->toBe($sebelum);
});
