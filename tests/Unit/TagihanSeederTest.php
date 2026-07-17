<?php
// tests/Unit/TagihanSeederTest.php

use App\Models\JalurPpdb;
use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\NominalTagihanJalur;
use App\Models\Pendaftaran;
use App\Models\Tagihan;
use App\Models\TahunAjaran;
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
});

it('sets total_tagihan to the real configured NominalTagihanJalur value for each lembaga, not a hardcoded amount', function () {
    (new TagihanSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    $sma = Lembaga::where('npsn', '20223355')->first();

    $aktifSmp = TahunAjaran::where('lembaga_id', $smp->id)->where('status_aktif', true)->first();
    $jalurSmp = JalurPpdb::where('lembaga_id', $smp->id)->where('tahun_ajaran_id', $aktifSmp->id)->where('nama', 'Reguler')->first();
    $jenisDaftarUlangSmp = JenisTagihan::where('lembaga_id', $smp->id)->where('nama', 'Uang Pangkal')->first();
    $nominalSmp = NominalTagihanJalur::where('jenis_tagihan_id', $jenisDaftarUlangSmp->id)->where('jalur_ppdb_id', $jalurSmp->id)->first();

    $diterimaSmp = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.diterima@example.test')->first();
    $tagihanDaftarUlangSmp = Tagihan::where('pendaftaran_id', $diterimaSmp->id)->where('kategori', 'daftar_ulang')->first();
    expect((int) $tagihanDaftarUlangSmp->total_tagihan)->toBe((int) $nominalSmp->nominal);

    $aktifSma = TahunAjaran::where('lembaga_id', $sma->id)->where('status_aktif', true)->first();
    $jalurSma = JalurPpdb::where('lembaga_id', $sma->id)->where('tahun_ajaran_id', $aktifSma->id)->where('nama', 'Reguler')->first();
    $jenisDaftarUlangSma = JenisTagihan::where('lembaga_id', $sma->id)->where('nama', 'Uang Pangkal')->first();
    $nominalSma = NominalTagihanJalur::where('jenis_tagihan_id', $jenisDaftarUlangSma->id)->where('jalur_ppdb_id', $jalurSma->id)->first();

    $diterimaSma = Pendaftaran::where('lembaga_id', $sma->id)->where('email_pendaftaran', 'wali.diterima@example.test')->first();
    $tagihanDaftarUlangSma = Tagihan::where('pendaftaran_id', $diterimaSma->id)->where('kategori', 'daftar_ulang')->first();
    expect((int) $tagihanDaftarUlangSma->total_tagihan)->toBe((int) $nominalSma->nominal);

    expect((int) $tagihanDaftarUlangSmp->total_tagihan)->not->toBe((int) $tagihanDaftarUlangSma->total_tagihan);
});

it('creates 2 tagihan for the diterima candidate and 1 for the cicilan-demo candidate, per lembaga', function () {
    (new TagihanSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    $diterima = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.diterima@example.test')->first();
    $cicilanDemo = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.cicilan-demo@example.test')->first();

    expect(Tagihan::where('pendaftaran_id', $diterima->id)->count())->toBe(2);
    expect(Tagihan::where('pendaftaran_id', $cicilanDemo->id)->count())->toBe(1);
    expect(Tagihan::where('pendaftaran_id', $cicilanDemo->id)->first()->kategori)->toBe('daftar_ulang');
});

it('is idempotent when run twice', function () {
    (new TagihanSeeder())->run();
    (new TagihanSeeder())->run();

    expect(Tagihan::count())->toBe(6);
});
