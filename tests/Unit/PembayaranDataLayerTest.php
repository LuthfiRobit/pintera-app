<?php

use App\Domains\Keuangan\Models\Cicilan;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Domains\Keuangan\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Domains\Keuangan\Models\SkemaCicilan;
use App\Domains\Keuangan\Models\Tagihan;
use App\Domains\Keuangan\Models\TagihanItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('links skema_cicilan, cicilan, and pembayaran back to their tagihan', function () {
    $lembaga = Lembaga::factory()->create();
    $pendaftaran = Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'status' => 'diterima']);
    $tagihan = Tagihan::create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => 3000000, 'status' => 'belum_bayar']);

    $skema = SkemaCicilan::create(['tagihan_id' => $tagihan->id, 'jumlah_termin' => 3, 'dibuat_oleh' => 'calon_siswa']);
    $cicilan1 = Cicilan::create(['skema_cicilan_id' => $skema->id, 'urutan' => 1, 'nominal' => 1000000, 'jatuh_tempo' => now()->addDays(30), 'status' => 'lunas']);
    Cicilan::create(['skema_cicilan_id' => $skema->id, 'urutan' => 2, 'nominal' => 1000000, 'jatuh_tempo' => now()->addDays(60), 'status' => 'belum_bayar']);
    Cicilan::create(['skema_cicilan_id' => $skema->id, 'urutan' => 3, 'nominal' => 1000000, 'jatuh_tempo' => now()->addDays(90), 'status' => 'belum_bayar']);
    Pembayaran::create(['cicilan_id' => $cicilan1->id, 'sumber' => 'calon_siswa', 'metode' => 'transfer_manual', 'status' => 'lunas']);

    expect($tagihan->fresh()->skemaCicilan->id)->toBe($skema->id);
    expect($tagihan->fresh()->cicilan)->toHaveCount(3);
    expect($skema->fresh()->cicilan->pluck('urutan')->all())->toBe([1, 2, 3]);
    expect($cicilan1->fresh()->pembayaran)->toHaveCount(1);
});

it('computes bisaDicicil/maksCicilan from the cheapest cicilable item, not a stored column', function () {
    $lembaga = Lembaga::factory()->create();
    $pendaftaran = Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $tagihan = Tagihan::create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => 500000, 'status' => 'belum_bayar']);
    $jenisTidakBisaDicicil = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Seragam', 'kategori' => 'daftar_ulang', 'bisa_dicicil' => false]);
    $jenisBisaDicicil = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Uang Pangkal', 'kategori' => 'daftar_ulang', 'bisa_dicicil' => true, 'maks_cicilan' => 3]);
    TagihanItem::create(['tagihan_id' => $tagihan->id, 'jenis_tagihan_id' => $jenisTidakBisaDicicil->id, 'jumlah' => 100000]);
    TagihanItem::create(['tagihan_id' => $tagihan->id, 'jenis_tagihan_id' => $jenisBisaDicicil->id, 'jumlah' => 400000]);

    $eligibility = app(\App\Domains\Keuangan\Services\TagihanCicilanEligibilityService::class);
    expect($eligibility->bisaDicicil($tagihan))->toBeTrue();
    expect($eligibility->maksCicilan($tagihan))->toBe(3);
});

it('reports isAktif false for a diterima pendaftaran with no lunas payment at all', function () {
    $lembaga = Lembaga::factory()->create();
    $pendaftaran = Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'status' => 'diterima']);
    Tagihan::create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => 3000000, 'status' => 'belum_bayar']);

    expect($pendaftaran->is_aktif)->toBeFalse();
});

it('reports isAktif true when the daftar_ulang tagihan is paid lunas without any cicilan', function () {
    $lembaga = Lembaga::factory()->create();
    $pendaftaran = Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'status' => 'diterima']);
    Tagihan::create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => 3000000, 'status' => 'lunas']);

    expect($pendaftaran->is_aktif)->toBeTrue();
});

it('reports isAktif true once only the first cicilan termin is lunas, even if later termin are still unpaid', function () {
    $lembaga = Lembaga::factory()->create();
    $pendaftaran = Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'status' => 'diterima']);
    $tagihan = Tagihan::create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => 3000000, 'status' => 'dicicil']);
    $skema = SkemaCicilan::create(['tagihan_id' => $tagihan->id, 'jumlah_termin' => 3, 'dibuat_oleh' => 'calon_siswa']);
    Cicilan::create(['skema_cicilan_id' => $skema->id, 'urutan' => 1, 'nominal' => 1000000, 'jatuh_tempo' => now(), 'status' => 'lunas']);
    Cicilan::create(['skema_cicilan_id' => $skema->id, 'urutan' => 2, 'nominal' => 1000000, 'jatuh_tempo' => now(), 'status' => 'belum_bayar']);
    Cicilan::create(['skema_cicilan_id' => $skema->id, 'urutan' => 3, 'nominal' => 1000000, 'jatuh_tempo' => now(), 'status' => 'belum_bayar']);

    expect($pendaftaran->fresh()->is_aktif)->toBeTrue();
});

it('reports isAktif false when status is not diterima even if a lunas daftar_ulang tagihan somehow exists', function () {
    $lembaga = Lembaga::factory()->create();
    $pendaftaran = Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'status' => 'ditolak']);
    Tagihan::create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => 0, 'status' => 'lunas']);

    expect($pendaftaran->is_aktif)->toBeFalse();
});
