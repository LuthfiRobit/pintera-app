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
    (new PermissionSeeder)->run();
    (new RoleSeeder)->run();
    Yayasan::factory()->create();
    (new LembagaSeeder)->run();
    (new EssentialUserSeeder)->run();
    (new UserSeeder)->run();
    (new TahunAjaranSeeder)->run();
    (new SemesterSeeder)->run();
    (new GuruSeeder)->run();
    (new PolaJamSeeder)->run();
    (new KelasSeeder)->run();
});

it('seeds students into active classes for the SD institution', function () {
    (new SiswaSeeder)->run();

    $sdit = Lembaga::where('npsn', '20223333')->first();
    $aktif = TahunAjaran::where('lembaga_id', $sdit->id)->where('status_aktif', true)->first();
    $kelasIds = Kelas::where('lembaga_id', $sdit->id)->where('tahun_ajaran_id', $aktif->id)->pluck('id');

    $siswaCount = Siswa::whereIn('kelas_id', $kelasIds)->count();
    expect($siswaCount)->toBe(336);

    $siswaWithUser = Siswa::whereIn('kelas_id', $kelasIds)->whereHas('person', fn ($q) => $q->whereNotNull('user_id'))->first();
    expect($siswaWithUser)->not->toBeNull();
    expect($siswaWithUser->user->hasRole('siswa'))->toBeTrue();

    $siswaPertama = Siswa::where('lembaga_id', $sdit->id)->where('nis', '3333001')->first();
    expect($siswaPertama)->not->toBeNull();
    expect($siswaPertama->nama_lengkap)->toBe('Muhammad Santoso');
});

it('is idempotent when run twice', function () {
    (new SiswaSeeder)->run();
    $sebelum = Siswa::count();
    (new SiswaSeeder)->run();

    expect(Siswa::count())->toBe($sebelum);
});
