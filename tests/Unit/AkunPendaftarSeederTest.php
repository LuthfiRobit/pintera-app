<?php
// tests/Unit/AkunPendaftarSeederTest.php

use App\Models\AkunPendaftar;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\Yayasan;
use Database\Seeders\AkunPendaftarSeeder;
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
use Illuminate\Support\Facades\Hash;
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
    (new PendaftaranSeeder())->run();
});

it('seeds one verified akun pendaftar per lembaga, attached to that lembaga diterima pendaftaran', function () {
    (new AkunPendaftarSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    $akunSmp = AkunPendaftar::where('email', 'pendaftar.smp@example.test')->first();

    expect($akunSmp)->not->toBeNull();
    expect($akunSmp->email_verified_at)->not->toBeNull();
    expect(Hash::check('password', $akunSmp->password))->toBeTrue();

    $diterimaSmp = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.diterima@example.test')->first();
    expect($diterimaSmp->fresh()->akun_pendaftar_id)->toBe($akunSmp->id);
    expect($akunSmp->pendaftaran()->count())->toBe(1);

    $sma = Lembaga::where('npsn', '20223355')->first();
    $akunSma = AkunPendaftar::where('email', 'pendaftar.sma@example.test')->first();
    expect($akunSma->id)->not->toBe($akunSmp->id);
});

it('is idempotent when run twice', function () {
    (new AkunPendaftarSeeder())->run();
    (new AkunPendaftarSeeder())->run();

    expect(AkunPendaftar::count())->toBe(2);
});
