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

it('links each pendaftaran to the correct calon murid and lembaga, with decision fields set for diterima/ditolak across all K-9 institutions', function () {
    (new PendaftaranSeeder())->run();

    foreach (Lembaga::all() as $lembaga) {
        $staf = User::where('lembaga_id', $lembaga->id)->first();

        $diterima = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.diterima@demo.test')->first();
        expect($diterima)->not->toBeNull();
        expect($diterima->calonMurid->nama_lengkap)->toBe('Calon Diterima ('.$lembaga->nama.')');
        expect($diterima->status)->toBe('diterima');
        expect($diterima->ditetapkan_oleh_user_id)->toBe($staf->id);

        $ditolak = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.ditolak@demo.test')->first();
        expect($ditolak->status)->toBe('ditolak');

        $menunggu = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.menunggu@demo.test')->first();
        expect($menunggu->status)->toBe('menunggu_verifikasi');

        $cicilanDemo = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.cicilan@demo.test')->first();
        expect($cicilanDemo->status)->toBe('diterima');
        expect($cicilanDemo->kode_pendaftaran)->toBe('REG-PEMBAYARAN-DEMO-'.$lembaga->id);
    }
});

it('does not mix up the same scenario email between different institutions', function () {
    (new PendaftaranSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    $sdit = Lembaga::where('npsn', '20223333')->first();

    $pendaftaranSmp = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.diterima@demo.test')->first();
    $pendaftaranSdit = Pendaftaran::where('lembaga_id', $sdit->id)->where('email_pendaftaran', 'wali.diterima@demo.test')->first();

    expect($pendaftaranSmp->id)->not->toBe($pendaftaranSdit->id);
    expect($pendaftaranSmp->calon_murid_id)->not->toBe($pendaftaranSdit->calon_murid_id);
});

it('is idempotent when run twice', function () {
    (new PendaftaranSeeder())->run();
    (new PendaftaranSeeder())->run();

    expect(Pendaftaran::count())->toBe(16);
});
