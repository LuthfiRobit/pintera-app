<?php
// tests/Unit/KeuanganDataLayerTest.php

use App\Models\JalurPpdb;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Domains\Keuangan\Models\NominalTagihanJalur;
use App\Models\Pendaftaran;
use App\Domains\Keuangan\Models\Tagihan;
use App\Domains\Keuangan\Models\TagihanItem;
use App\Models\TahunAjaran;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('creates a jenis_tagihan scoped to the acting lembaga-scoped user', function () {
    Role::firstOrCreate(['name' => 'bendahara_lembaga', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('bendahara_lembaga');

    $this->actingAs($user);
    $jenisTagihan = JenisTagihan::create([
        'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false, 'maks_cicilan' => null,
    ]);

    expect($jenisTagihan->fresh()->lembaga_id)->toBe($lembaga->id);

    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $userLain = User::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $userLain->assignRole('bendahara_lembaga');
    $this->actingAs($userLain);
    expect(JenisTagihan::find($jenisTagihan->id))->toBeNull();
});

it('exposes nominal_tagihan_jalur enforcing a unique jenis_tagihan+jalur pair', function () {
    $lembaga = Lembaga::factory()->create();
    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);
    $jenisTagihan = JenisTagihan::create([
        'lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false,
    ]);

    NominalTagihanJalur::create(['jenis_tagihan_id' => $jenisTagihan->id, 'jalur_ppdb_id' => $jalur->id, 'nominal' => 150000]);

    expect(fn () => NominalTagihanJalur::create(['jenis_tagihan_id' => $jenisTagihan->id, 'jalur_ppdb_id' => $jalur->id, 'nominal' => 200000]))
        ->toThrow(\Illuminate\Database\QueryException::class);

    expect($jenisTagihan->nominalJalur()->count())->toBe(1);
});

it('links a tagihan and its items back to the pendaftaran that owns them', function () {
    $lembaga = Lembaga::factory()->create();
    $pendaftaran = Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisTagihan = JenisTagihan::create([
        'lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false,
    ]);

    $tagihan = Tagihan::create([
        'pendaftaran_id' => $pendaftaran->id, 'kategori' => 'pendaftaran', 'total_tagihan' => 150000, 'status' => 'belum_bayar',
    ]);
    TagihanItem::create(['tagihan_id' => $tagihan->id, 'jenis_tagihan_id' => $jenisTagihan->id, 'jumlah' => 150000]);

    expect($pendaftaran->fresh()->tagihan)->toHaveCount(1);
    expect($tagihan->fresh()->item)->toHaveCount(1);
    expect($tagihan->fresh()->item->first()->jenisTagihan->nama)->toBe('Biaya Pendaftaran');
});
