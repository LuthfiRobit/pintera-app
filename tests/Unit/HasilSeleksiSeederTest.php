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

    // Test SMP (NPSN 20223344)
    $smp = Lembaga::where('npsn', '20223344')->first();

    $diterimaSmp = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.diterima@example.test')->first();
    $nilaiDiterimaSmp = HasilSeleksi::where('pendaftaran_id', $diterimaSmp->id)->get();
    expect($nilaiDiterimaSmp)->not->toBeEmpty();
    foreach ($nilaiDiterimaSmp as $hasil) {
        expect((float) $hasil->nilai)->toBeGreaterThanOrEqual(75)->toBeLessThanOrEqual(95);
    }

    $ditolakSmp = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.ditolak@example.test')->first();
    $nilaiDitolakSmp = HasilSeleksi::where('pendaftaran_id', $ditolakSmp->id)->get();
    expect($nilaiDitolakSmp)->not->toBeEmpty();
    foreach ($nilaiDitolakSmp as $hasil) {
        expect((float) $hasil->nilai)->toBeGreaterThanOrEqual(30)->toBeLessThanOrEqual(55);
    }

    // Test SMA (NPSN 20223355)
    $sma = Lembaga::where('npsn', '20223355')->first();

    $diterimaSma = Pendaftaran::where('lembaga_id', $sma->id)->where('email_pendaftaran', 'wali.diterima@example.test')->first();
    $nilaiDiterimaSma = HasilSeleksi::where('pendaftaran_id', $diterimaSma->id)->get();
    expect($nilaiDiterimaSma)->not->toBeEmpty();
    foreach ($nilaiDiterimaSma as $hasil) {
        expect((float) $hasil->nilai)->toBeGreaterThanOrEqual(75)->toBeLessThanOrEqual(95);
    }

    $ditolakSma = Pendaftaran::where('lembaga_id', $sma->id)->where('email_pendaftaran', 'wali.ditolak@example.test')->first();
    $nilaiDitolakSma = HasilSeleksi::where('pendaftaran_id', $ditolakSma->id)->get();
    expect($nilaiDitolakSma)->not->toBeEmpty();
    foreach ($nilaiDitolakSma as $hasil) {
        expect((float) $hasil->nilai)->toBeGreaterThanOrEqual(30)->toBeLessThanOrEqual(55);
    }
});

it('is idempotent when run twice', function () {
    (new HasilSeleksiSeeder())->run();
    $sebelum = HasilSeleksi::count();
    (new HasilSeleksiSeeder())->run();

    expect(HasilSeleksi::count())->toBe($sebelum);
});
