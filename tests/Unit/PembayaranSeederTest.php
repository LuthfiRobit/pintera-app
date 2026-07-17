<?php
// tests/Unit/PembayaranSeederTest.php

use App\Models\Lembaga;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Models\Tagihan;
use App\Models\TagihanItem;
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
use Database\Seeders\PembayaranSeeder;
use Database\Seeders\PendaftaranSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SemesterSeeder;
use Database\Seeders\SkemaCicilanSeeder;
use Database\Seeders\SkPpdbSeeder;
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
    (new SkPpdbSeeder())->run();
    (new TagihanSeeder())->run();
    (new TagihanItemSeeder())->run();
    (new SkemaCicilanSeeder())->run();
    (new CicilanSeeder())->run();
});

it('creates a pending payment for each of the diterima candidate 2 tagihan, per lembaga', function () {
    (new PembayaranSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    $diterima = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.diterima@example.test')->first();

    foreach (Tagihan::where('pendaftaran_id', $diterima->id)->get() as $tagihan) {
        $pembayaran = Pembayaran::where('tagihan_id', $tagihan->id)->first();
        expect($pembayaran)->not->toBeNull();
        expect($pembayaran->status)->toBe('menunggu_verifikasi');
        expect($pembayaran->sumber)->toBe('calon_siswa');
    }
});

it('creates a pending payment for termin 1 of the cicilan-demo candidate, per lembaga', function () {
    (new PembayaranSeeder())->run();

    $smp = Lembaga::where('npsn', '20223344')->first();
    $cicilanDemo = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.cicilan-demo@example.test')->first();
    $tagihan = Tagihan::where('pendaftaran_id', $cicilanDemo->id)->where('kategori', 'daftar_ulang')->first();
    $termin1 = $tagihan->skemaCicilan->cicilan()->where('urutan', 1)->first();

    $pembayaran = Pembayaran::where('cicilan_id', $termin1->id)->first();
    expect($pembayaran)->not->toBeNull();
    expect($pembayaran->status)->toBe('menunggu_verifikasi');
});

it('traces the full chain from a diterima pendaftaran through to its pembayaran, for both lembaga without cross-lembaga mixups', function () {
    (new PembayaranSeeder())->run();

    foreach (['20223344' => 'SMP', '20223355' => 'SMA'] as $npsn => $label) {
        $lembaga = Lembaga::where('npsn', $npsn)->first();
        $diterima = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.diterima@example.test')->first();

        expect($diterima->calonMurid->nama_lengkap)->toBe('Calon Diterima ('.$lembaga->nama.')');
        expect($diterima->lembaga_id)->toBe($lembaga->id);
        expect($diterima->skPpdb->lembaga_id)->toBe($lembaga->id);

        $tagihanDaftarUlang = Tagihan::where('pendaftaran_id', $diterima->id)->where('kategori', 'daftar_ulang')->first();
        expect($tagihanDaftarUlang)->not->toBeNull();

        $item = TagihanItem::where('tagihan_id', $tagihanDaftarUlang->id)->first();
        expect($item->jenisTagihan->lembaga_id)->toBe($lembaga->id);
        expect((int) $item->jumlah)->toBe((int) $tagihanDaftarUlang->total_tagihan);

        $pembayaran = Pembayaran::where('tagihan_id', $tagihanDaftarUlang->id)->first();
        expect($pembayaran->tagihan->pendaftaran->id)->toBe($diterima->id);
    }

    $smp = Lembaga::where('npsn', '20223344')->first();
    $sma = Lembaga::where('npsn', '20223355')->first();
    $diterimaSmp = Pendaftaran::where('lembaga_id', $smp->id)->where('email_pendaftaran', 'wali.diterima@example.test')->first();
    $diterimaSma = Pendaftaran::where('lembaga_id', $sma->id)->where('email_pendaftaran', 'wali.diterima@example.test')->first();

    expect($diterimaSmp->sk_ppdb_id)->not->toBe($diterimaSma->sk_ppdb_id);
    expect($diterimaSmp->calon_murid_id)->not->toBe($diterimaSma->calon_murid_id);
});

it('is idempotent when run twice', function () {
    (new PembayaranSeeder())->run();
    (new PembayaranSeeder())->run();

    expect(Pembayaran::count())->toBe(6);
});
