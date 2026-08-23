<?php
// tests/Unit/TagihanGeneratorTest.php

use App\Models\JalurPpdb;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\NominalTagihanJalur;
use App\Models\Pendaftaran;
use App\Models\Tagihan;
use App\Services\TagihanGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function siapkanPendaftaranUntukInvoicing(): array
{
    $lembaga = Lembaga::factory()->create();
    $tahunAjaran = \App\Models\TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);
    $pendaftaran = Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'jalur_ppdb_id' => $jalur->id]);

    return [$lembaga, $jalur, $pendaftaran];
}

it('creates a tagihan with items summing to the configured nominal', function () {
    [$lembaga, $jalur, $pendaftaran] = siapkanPendaftaranUntukInvoicing();
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);
    NominalTagihanJalur::create(['jenis_tagihan_id' => $jenisTagihan->id, 'jalur_ppdb_id' => $jalur->id, 'nominal' => 150000]);

    $tagihan = app(TagihanGenerator::class)->generate($pendaftaran, 'pendaftaran');

    expect($tagihan)->not->toBeNull();
    expect((float) $tagihan->total_tagihan)->toBe(150000.0);
    expect($tagihan->status)->toBe('belum_bayar');
    expect($tagihan->item)->toHaveCount(1);
});

it('creates no tagihan at all when nothing is configured for this jalur', function () {
    [, , $pendaftaran] = siapkanPendaftaranUntukInvoicing();

    $tagihan = app(TagihanGenerator::class)->generate($pendaftaran, 'pendaftaran');

    expect($tagihan)->toBeNull();
    $this->assertDatabaseMissing('tagihan', ['pendaftaran_id' => $pendaftaran->id]);
});

it('creates a tagihan with only the partially-configured items, not all-or-nothing', function () {
    [$lembaga, $jalur, $pendaftaran] = siapkanPendaftaranUntukInvoicing();
    $dikonfigurasi = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Uang Pangkal', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);
    JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Seragam', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]); // no nominal set for this one
    NominalTagihanJalur::create(['jenis_tagihan_id' => $dikonfigurasi->id, 'jalur_ppdb_id' => $jalur->id, 'nominal' => 500000]);

    $tagihan = app(TagihanGenerator::class)->generate($pendaftaran, 'pendaftaran');

    expect($tagihan)->not->toBeNull();
    expect($tagihan->item)->toHaveCount(1);
    expect((float) $tagihan->total_tagihan)->toBe(500000.0);
});

it('marks the tagihan lunas immediately when every configured item is genuinely zero', function () {
    [$lembaga, $jalur, $pendaftaran] = siapkanPendaftaranUntukInvoicing();
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran Afirmasi', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);
    NominalTagihanJalur::create(['jenis_tagihan_id' => $jenisTagihan->id, 'jalur_ppdb_id' => $jalur->id, 'nominal' => 0]);

    $tagihan = app(TagihanGenerator::class)->generate($pendaftaran, 'pendaftaran');

    expect($tagihan->status)->toBe('lunas');
});

it('is idempotent: a second call for the same pendaftaran and kategori creates nothing and returns null', function () {
    [$lembaga, $jalur, $pendaftaran] = siapkanPendaftaranUntukInvoicing();
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);
    NominalTagihanJalur::create(['jenis_tagihan_id' => $jenisTagihan->id, 'jalur_ppdb_id' => $jalur->id, 'nominal' => 150000]);
    $generator = app(TagihanGenerator::class);

    $pertama = $generator->generate($pendaftaran, 'pendaftaran');
    $kedua = $generator->generate($pendaftaran, 'pendaftaran');

    expect($pertama)->not->toBeNull();
    expect($kedua)->toBeNull();
    expect(Tagihan::where('pendaftaran_id', $pendaftaran->id)->where('kategori', 'pendaftaran')->count())->toBe(1);
});

it('ignores jenis_tagihan of a different kategori than requested', function () {
    [$lembaga, $jalur, $pendaftaran] = siapkanPendaftaranUntukInvoicing();
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Uang Pangkal', 'kategori' => 'daftar_ulang', 'bisa_dicicil' => false]);
    NominalTagihanJalur::create(['jenis_tagihan_id' => $jenisTagihan->id, 'jalur_ppdb_id' => $jalur->id, 'nominal' => 1000000]);

    $tagihan = app(TagihanGenerator::class)->generate($pendaftaran, 'pendaftaran');

    expect($tagihan)->toBeNull();
});
