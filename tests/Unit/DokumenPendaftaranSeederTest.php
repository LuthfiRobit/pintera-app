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

it('seeds mixed-status dokumen for the menunggu-verifikasi pendaftaran and all-diterima for the diterima pendaftaran across all K-9 institutions', function () {
    (new DokumenPendaftaranSeeder())->run();

    foreach (Lembaga::all() as $lembaga) {
        $jalur = JalurPpdb::where('lembaga_id', $lembaga->id)->where('nama', 'Reguler')
            ->whereHas('tahunAjaran', fn ($q) => $q->where('status_aktif', true))->first();
        $jumlahSyarat = $jalur->dokumenSyarat()->count();
        expect($jumlahSyarat)->toBeGreaterThan(0);

        $menunggu = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.menunggu@demo.test')->first();
        $dokumenMenunggu = DokumenPendaftaran::where('pendaftaran_id', $menunggu->id)->get();
        expect($dokumenMenunggu)->toHaveCount($jumlahSyarat);
        expect($dokumenMenunggu->pluck('status_verifikasi')->unique()->count())->toBeGreaterThan(1);

        $diterima = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.diterima@demo.test')->first();
        $dokumenDiterima = DokumenPendaftaran::where('pendaftaran_id', $diterima->id)->get();
        expect($dokumenDiterima)->toHaveCount($jumlahSyarat);
        expect($dokumenDiterima->pluck('status_verifikasi')->unique()->all())->toBe(['diterima']);
    }
});

it('is idempotent when run twice', function () {
    (new DokumenPendaftaranSeeder())->run();
    $sebelum = DokumenPendaftaran::count();
    (new DokumenPendaftaranSeeder())->run();

    expect(DokumenPendaftaran::count())->toBe($sebelum);
});
