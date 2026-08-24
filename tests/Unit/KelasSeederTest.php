<?php
// tests/Unit/KelasSeederTest.php

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Domains\Akademik\Models\PolaJam;
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
});

it('seeds appropriate classes for the SD institution and links them to PolaJam and Wali Kelas', function () {
    (new KelasSeeder())->run();

    foreach (Lembaga::all() as $lembaga) {
        $aktif = TahunAjaran::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first();
        $polaJam = PolaJam::where('lembaga_id', $lembaga->id)->first();
        $kelasAktif = Kelas::where('lembaga_id', $lembaga->id)->where('tahun_ajaran_id', $aktif->id)->get();

        $expectedNames = match ($lembaga->bentuk_pendidikan) {
            'SD' => ['Kelas 1-A', 'Kelas 1-B', 'Kelas 2-A', 'Kelas 2-B', 'Kelas 3-A', 'Kelas 3-B', 'Kelas 4-A', 'Kelas 4-B', 'Kelas 5-A', 'Kelas 5-B', 'Kelas 6-A', 'Kelas 6-B'],
            default => [],
        };

        expect($kelasAktif->pluck('nama')->toArray())->toEqualCanonicalizing($expectedNames);

        $hasGuru = Guru::where('lembaga_id', $lembaga->id)->exists();
        foreach ($kelasAktif as $k) {
            expect($k->pola_jam_id)->toBe($polaJam->id);
            if ($hasGuru) {
                expect($k->wali_kelas_guru_id)->not->toBeNull();
            }
        }
    }
});

it('is idempotent when run twice', function () {
    (new KelasSeeder())->run();
    $sebelum = Kelas::count();
    (new KelasSeeder())->run();

    expect(Kelas::count())->toBe($sebelum);
    // Across 2 tahun ajaran, 1 lembaga (SD): 12 kelas * 2 = 24 kelas total
    expect(Kelas::count())->toBe(24);
});
