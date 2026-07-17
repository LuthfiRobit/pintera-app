<?php
// tests/Unit/PendaftaranSeederTest.php

use App\Models\CalonMurid;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\CalonMuridSeeder;
use Database\Seeders\EssentialUserSeeder;
use Database\Seeders\GelombangPpdbSeeder;
use Database\Seeders\JalurPpdbSeeder;
use Database\Seeders\JenisTesMasterSeeder;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\PendaftaranSeeder;
use Database\Seeders\PermissionSeeder;
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
    (new JenisTesMasterSeeder())->run();
    (new GelombangPpdbSeeder())->run();
    (new JalurPpdbSeeder())->run();
    (new CalonMuridSeeder())->run();
});

it('links each pendaftaran to the correct calon murid and lembaga, with decision fields set for diterima/ditolak', function () {
    (new PendaftaranSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    $staf = User::where('lembaga_id', $smp->id)->first();

    $diterima = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.diterima@example.test')->first();
    expect($diterima)->not->toBeNull();
    expect($diterima->calonMurid->nama_lengkap)->toBe('Calon Diterima ('.$smp->nama.')');
    expect($diterima->status)->toBe('diterima');
    expect($diterima->ditetapkan_oleh_user_id)->toBe($staf->id);

    $ditolak = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.ditolak@example.test')->first();
    expect($ditolak->status)->toBe('ditolak');

    $menunggu = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.menunggu@example.test')->first();
    expect($menunggu->status)->toBe('menunggu_verifikasi');

    $cicilanDemo = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.cicilan-demo@example.test')->first();
    expect($cicilanDemo->status)->toBe('diterima');
    expect($cicilanDemo->kode_pendaftaran)->toBe('REG-PEMBAYARAN-DEMO-'.$smp->id);
});

it('does not mix up the same scenario email between SMP and SMA', function () {
    (new PendaftaranSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    $sma = Lembaga::where('npsn', '20223355')->first();

    $pendaftaranSmp = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.diterima@example.test')->first();
    $pendaftaranSma = Pendaftaran::where('lembaga_id', $sma->id)->where('email_pendaftaran', 'wali.diterima@example.test')->first();

    expect($pendaftaranSmp->id)->not->toBe($pendaftaranSma->id);
    expect($pendaftaranSmp->calon_murid_id)->not->toBe($pendaftaranSma->calon_murid_id);
});

it('is idempotent when run twice', function () {
    (new PendaftaranSeeder())->run();
    (new PendaftaranSeeder())->run();

    expect(Pendaftaran::count())->toBe(8);
});
