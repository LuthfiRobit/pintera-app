<?php
// tests/Unit/SkPpdbSeederTest.php

use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\SkPpdb;
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
use Database\Seeders\SkPpdbSeeder;
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
    (new PendaftaranSeeder())->run();
});

it('creates one SK per lembaga and attaches it to both the diterima and ditolak pendaftaran of that lembaga', function () {
    (new SkPpdbSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    $sma = Lembaga::where('npsn', '20223355')->first();

    $skSmp = SkPpdb::where('lembaga_id', $smp->id)->first();
    expect($skSmp)->not->toBeNull();

    $diterimaSmp = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.diterima@example.test')->first();
    $ditolakSmp = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.ditolak@example.test')->first();
    expect($diterimaSmp->sk_ppdb_id)->toBe($skSmp->id);
    expect($ditolakSmp->sk_ppdb_id)->toBe($skSmp->id);

    $skSma = SkPpdb::where('lembaga_id', $sma->id)->first();
    expect($skSma->id)->not->toBe($skSmp->id);
});

it('is idempotent when run twice', function () {
    (new SkPpdbSeeder())->run();
    (new SkPpdbSeeder())->run();

    expect(SkPpdb::count())->toBe(2);
});
