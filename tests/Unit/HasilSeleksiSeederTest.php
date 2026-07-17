<?php
// tests/Unit/HasilSeleksiSeederTest.php

use App\Models\HasilSeleksi;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\Yayasan;
use Database\Seeders\CalonMuridSeeder;
use Database\Seeders\DokumenSyaratPpdbSeeder;
use Database\Seeders\EssentialUserSeeder;
use Database\Seeders\FormulirFieldSeeder;
use Database\Seeders\GelombangPpdbSeeder;
use Database\Seeders\HasilSeleksiSeeder;
use Database\Seeders\JalurPpdbSeeder;
use Database\Seeders\JenisTesMasterSeeder;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\PendaftaranSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SemesterSeeder;
use Database\Seeders\SeleksiPpdbSeeder;
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
    (new JenisTesMasterSeeder())->run();
    (new GelombangPpdbSeeder())->run();
    (new JalurPpdbSeeder())->run();
    (new FormulirFieldSeeder())->run();
    (new DokumenSyaratPpdbSeeder())->run();
    (new SeleksiPpdbSeeder())->run();
    (new CalonMuridSeeder())->run();
    (new PendaftaranSeeder())->run();
});

it('seeds passing-range nilai for diterima and failing-range nilai for ditolak', function () {
    (new HasilSeleksiSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();

    $diterima = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.diterima@example.test')->first();
    $nilaiDiterima = HasilSeleksi::where('pendaftaran_id', $diterima->id)->get();
    expect($nilaiDiterima)->not->toBeEmpty();
    foreach ($nilaiDiterima as $hasil) {
        expect((float) $hasil->nilai)->toBeGreaterThanOrEqual(75)->toBeLessThanOrEqual(95);
    }

    $ditolak = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.ditolak@example.test')->first();
    $nilaiDitolak = HasilSeleksi::where('pendaftaran_id', $ditolak->id)->get();
    expect($nilaiDitolak)->not->toBeEmpty();
    foreach ($nilaiDitolak as $hasil) {
        expect((float) $hasil->nilai)->toBeGreaterThanOrEqual(30)->toBeLessThanOrEqual(55);
    }
});

it('is idempotent when run twice', function () {
    (new HasilSeleksiSeeder())->run();
    $sebelum = HasilSeleksi::count();
    (new HasilSeleksiSeeder())->run();

    expect(HasilSeleksi::count())->toBe($sebelum);
});
