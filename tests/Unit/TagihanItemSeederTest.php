<?php
// tests/Unit/TagihanItemSeederTest.php

use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\Tagihan;
use App\Domains\Keuangan\Models\TagihanItem;
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
use Database\Seeders\TagihanItemSeeder;
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

it('creates exactly one item per tagihan across all K-9 institutions, with jumlah matching total_tagihan', function () {
    (new TagihanItemSeeder())->run();

    foreach (Lembaga::all() as $lembaga) {
        $diterima = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.diterima@demo.test')->first();

        foreach (Tagihan::where('pendaftaran_id', $diterima->id)->get() as $tagihan) {
            $items = TagihanItem::where('tagihan_id', $tagihan->id)->get();
            expect($items)->toHaveCount(1);
            expect((int) $items->first()->jumlah)->toBe((int) $tagihan->total_tagihan);
        }
    }
});

it('is idempotent when run twice', function () {
    (new TagihanItemSeeder())->run();
    (new TagihanItemSeeder())->run();

    expect(TagihanItem::count())->toBe(12);
});
