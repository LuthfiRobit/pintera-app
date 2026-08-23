<?php
// tests/Unit/SkemaCicilanSeederTest.php

use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Domains\Keuangan\Models\SkemaCicilan;
use App\Domains\Keuangan\Models\Tagihan;
use App\Models\Yayasan;
use Database\Seeders\CalonMuridSeeder;
use Database\Seeders\EssentialUserSeeder;
use Database\Seeders\GelombangPpdbSeeder;
use Database\Seeders\JalurPpdbSeeder;
use Database\Seeders\JenisTagihanSeeder;
use Database\Seeders\JenisTesMasterSeeder;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\NominalTagihanJalurSeeder;
use Database\Seeders\PendaftaranSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SemesterSeeder;
use Database\Seeders\SkemaCicilanSeeder;
use Database\Seeders\TagihanSeeder;
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
    (new JenisTagihanSeeder())->run();
    (new NominalTagihanJalurSeeder())->run();
    (new CalonMuridSeeder())->run();
    (new PendaftaranSeeder())->run();
    (new TagihanSeeder())->run();
});

it('creates a 3-termin skema cicilan per lembaga for the cicilan-demo tagihan across all K-9 institutions', function () {
    (new SkemaCicilanSeeder())->run();

    foreach (Lembaga::all() as $lembaga) {
        $cicilanDemo = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.cicilan@demo.test')->first();
        $tagihan = Tagihan::where('pendaftaran_id', $cicilanDemo->id)->where('kategori', 'daftar_ulang')->first();

        $skema = SkemaCicilan::where('tagihan_id', $tagihan->id)->first();
        expect($skema)->not->toBeNull();
        expect($skema->jumlah_termin)->toBe(3);
        expect($tagihan->fresh()->status)->toBe('dicicil');
    }
});

it('is idempotent when run twice', function () {
    (new SkemaCicilanSeeder())->run();
    (new SkemaCicilanSeeder())->run();

    expect(SkemaCicilan::count())->toBe(4);
});
