<?php
// tests/Unit/TagihanSeederTest.php

use App\Models\JalurPpdb;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Domains\Keuangan\Models\NominalTagihanJalur;
use App\Models\Pendaftaran;
use App\Domains\Keuangan\Models\Tagihan;
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

it('sets total_tagihan to the real configured NominalTagihanJalur value, distinct per lembaga', function () {
    $lembagaKedua = Lembaga::factory()->create(['yayasan_id' => Lembaga::first()->yayasan_id]);

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

    $sdit = Lembaga::where('npsn', '20223333')->first();

    $aktifSdit = TahunAjaran::where('lembaga_id', $sdit->id)->where('status_aktif', true)->first();
    $jalurSdit = JalurPpdb::where('lembaga_id', $sdit->id)->where('tahun_ajaran_id', $aktifSdit->id)->where('nama', 'Reguler')->first();
    $jenisDaftarUlangSdit = JenisTagihan::where('lembaga_id', $sdit->id)->where('nama', 'Uang Pangkal')->first();
    $nominalSdit = NominalTagihanJalur::where('jenis_tagihan_id', $jenisDaftarUlangSdit->id)->where('jalur_ppdb_id', $jalurSdit->id)->first();

    $diterimaSdit = Pendaftaran::where('lembaga_id', $sdit->id)->where('email_pendaftaran', 'wali.diterima@demo.test')->first();
    $tagihanDaftarUlangSdit = Tagihan::where('pendaftaran_id', $diterimaSdit->id)->where('kategori', 'daftar_ulang')->first();
    expect((int) $tagihanDaftarUlangSdit->total_tagihan)->toBe((int) $nominalSdit->nominal);

    $aktifKedua = TahunAjaran::where('lembaga_id', $lembagaKedua->id)->where('status_aktif', true)->first();
    $jalurKedua = JalurPpdb::where('lembaga_id', $lembagaKedua->id)->where('tahun_ajaran_id', $aktifKedua->id)->where('nama', 'Reguler')->first();
    $jenisDaftarUlangKedua = JenisTagihan::where('lembaga_id', $lembagaKedua->id)->where('nama', 'Uang Pangkal')->first();
    $nominalKedua = NominalTagihanJalur::where('jenis_tagihan_id', $jenisDaftarUlangKedua->id)->where('jalur_ppdb_id', $jalurKedua->id)->first();

    $diterimaKedua = Pendaftaran::where('lembaga_id', $lembagaKedua->id)->where('email_pendaftaran', 'wali.diterima@demo.test')->first();
    $tagihanDaftarUlangKedua = Tagihan::where('pendaftaran_id', $diterimaKedua->id)->where('kategori', 'daftar_ulang')->first();
    expect((int) $tagihanDaftarUlangKedua->total_tagihan)->toBe((int) $nominalKedua->nominal);
});

it('creates 2 tagihan for the diterima candidate and 1 for the cicilan-demo candidate', function () {
    (new TagihanSeeder())->run();

    foreach (Lembaga::all() as $lembaga) {
        $diterima = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.diterima@demo.test')->first();
        $cicilanDemo = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.cicilan@demo.test')->first();

        expect(Tagihan::where('pendaftaran_id', $diterima->id)->count())->toBe(2);
        expect(Tagihan::where('pendaftaran_id', $cicilanDemo->id)->count())->toBe(1);
        expect(Tagihan::where('pendaftaran_id', $cicilanDemo->id)->first()->kategori)->toBe('daftar_ulang');
    }
});

it('is idempotent when run twice', function () {
    (new TagihanSeeder())->run();
    (new TagihanSeeder())->run();

    expect(Tagihan::count())->toBe(3);
});
