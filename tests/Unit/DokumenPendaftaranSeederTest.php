<?php
// tests/Unit/DokumenPendaftaranSeederTest.php

use App\Models\DokumenPendaftaran;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\CalonMuridSeeder;
use Database\Seeders\DokumenPendaftaranSeeder;
use Database\Seeders\DokumenSyaratPpdbSeeder;
use Database\Seeders\EssentialUserSeeder;
use Database\Seeders\FormulirFieldSeeder;
use Database\Seeders\GelombangPpdbSeeder;
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

it('seeds mixed-status dokumen for the menunggu-verifikasi pendaftaran and all-diterima for the diterima pendaftaran', function () {
    (new DokumenPendaftaranSeeder())->run();

    // Test SMP (NPSN 20223344)
    $smp = Lembaga::where('npsn', '20223344')->first();
    $jalurSmp = JalurPpdb::where('lembaga_id', $smp->id)->where('nama', 'Reguler')
        ->whereHas('tahunAjaran', fn ($q) => $q->where('status_aktif', true))->first();
    $jumlahSyaratSmp = $jalurSmp->dokumenSyarat()->count();

    $menungguSmp = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.menunggu@example.test')->first();
    $dokumenMenungguSmp = DokumenPendaftaran::where('pendaftaran_id', $menungguSmp->id)->get();
    expect($dokumenMenungguSmp)->toHaveCount($jumlahSyaratSmp);
    expect($dokumenMenungguSmp->pluck('status_verifikasi')->unique()->count())->toBeGreaterThan(1);

    $diterimaSmp = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.diterima@example.test')->first();
    $dokumenDiterimaSmp = DokumenPendaftaran::where('pendaftaran_id', $diterimaSmp->id)->get();
    expect($dokumenDiterimaSmp)->toHaveCount($jumlahSyaratSmp);
    expect($dokumenDiterimaSmp->pluck('status_verifikasi')->unique()->all())->toBe(['diterima']);

    // Test SMA (NPSN 20223355)
    $sma = Lembaga::where('npsn', '20223355')->first();
    $jalurSma = JalurPpdb::where('lembaga_id', $sma->id)->where('nama', 'Reguler')
        ->whereHas('tahunAjaran', fn ($q) => $q->where('status_aktif', true))->first();
    $jumlahSyaratSma = $jalurSma->dokumenSyarat()->count();

    $menungguSma = Pendaftaran::where('lembaga_id', $sma->id)->where('email_pendaftaran', 'wali.menunggu@example.test')->first();
    $dokumenMenungguSma = DokumenPendaftaran::where('pendaftaran_id', $menungguSma->id)->get();
    expect($dokumenMenungguSma)->toHaveCount($jumlahSyaratSma);
    expect($dokumenMenungguSma->pluck('status_verifikasi')->unique()->count())->toBeGreaterThan(1);

    $diterimaSma = Pendaftaran::where('lembaga_id', $sma->id)->where('email_pendaftaran', 'wali.diterima@example.test')->first();
    $dokumenDiterimaSma = DokumenPendaftaran::where('pendaftaran_id', $diterimaSma->id)->get();
    expect($dokumenDiterimaSma)->toHaveCount($jumlahSyaratSma);
    expect($dokumenDiterimaSma->pluck('status_verifikasi')->unique()->all())->toBe(['diterima']);
});

it('is idempotent when run twice', function () {
    (new DokumenPendaftaranSeeder())->run();
    $sebelum = DokumenPendaftaran::count();
    (new DokumenPendaftaranSeeder())->run();

    expect(DokumenPendaftaran::count())->toBe($sebelum);
});
