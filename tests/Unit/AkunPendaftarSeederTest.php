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

it('seeds one verified akun pendaftar per lembaga across all K-9 institutions, attached to that lembaga diterima pendaftaran', function () {
    (new AkunPendaftarSeeder())->run();

    $emailPerNpsn = [
        '20223311' => 'pendaftar.kb@demo.test',
        '20223322' => 'pendaftar.tk@demo.test',
        '20223333' => 'pendaftar.sd@demo.test',
        '20223344' => 'pendaftar.smp@demo.test',
    ];

    foreach (Lembaga::all() as $lembaga) {
        $email = $emailPerNpsn[$lembaga->npsn];
        $akun = AkunPendaftar::where('email', $email)->first();

        expect($akun)->not->toBeNull();
        expect($akun->email_verified_at)->not->toBeNull();
        expect(Hash::check('password', $akun->password))->toBeTrue();

        $diterima = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.diterima@demo.test')->first();
        expect($diterima->fresh()->akun_pendaftar_id)->toBe($akun->id);
        expect($akun->pendaftaran()->count())->toBe(1);
    }
});

it('is idempotent when run twice', function () {
    (new AkunPendaftarSeeder())->run();
    (new AkunPendaftarSeeder())->run();

    expect(AkunPendaftar::count())->toBe(4);
});
