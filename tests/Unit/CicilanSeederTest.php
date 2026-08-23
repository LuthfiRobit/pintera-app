<?php
// tests/Unit/CicilanSeederTest.php

use App\Models\Cicilan;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Domains\Keuangan\Models\Tagihan;
use App\Models\Yayasan;
use Database\Seeders\CalonMuridSeeder;
use Database\Seeders\CicilanSeeder;
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

it('does not error when the skema cicilan already has its 3 termin rows from SkemaCicilanSeeder across all K-9 institutions', function () {
    (new SkemaCicilanSeeder())->run();

    (new CicilanSeeder())->run();

    expect(Cicilan::count())->toBe(12);

    foreach (Lembaga::all() as $lembaga) {
        $cicilanDemo = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.cicilan@demo.test')->first();
        $tagihan = Tagihan::where('pendaftaran_id', $cicilanDemo->id)->where('kategori', 'daftar_ulang')->first();
        $urutanList = $tagihan->skemaCicilan->cicilan()->orderBy('urutan')->pluck('urutan')->all();

        expect($urutanList)->toBe([1, 2, 3]);
    }
});

it('throws if SkemaCicilanSeeder has not run yet (ordering invariant guard)', function () {
    (new CicilanSeeder())->run();
})->throws(RuntimeException::class);
