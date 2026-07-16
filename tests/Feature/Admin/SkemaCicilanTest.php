<?php
// tests/Feature/Admin/SkemaCicilanTest.php

use App\Models\Cicilan;
use App\Models\JenisTagihan;
use App\Models\NominalTagihanJalur;
use App\Models\SkemaCicilan;
use App\Models\Tagihan;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

function siapkanTagihanDaftarUlangBisaDicicil(): array
{
    [$lembaga, $jalur, , $pendaftaran] = buatPendaftaranUntukAdmin(status: 'diterima');
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Uang Pangkal', 'kategori' => 'daftar_ulang', 'bisa_dicicil' => true, 'maks_cicilan' => 3]);
    NominalTagihanJalur::create(['jenis_tagihan_id' => $jenisTagihan->id, 'jalur_ppdb_id' => $jalur->id, 'nominal' => 900000]);
    $tagihan = Tagihan::create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => 900000, 'status' => 'belum_bayar']);
    \App\Models\TagihanItem::create(['tagihan_id' => $tagihan->id, 'jenis_tagihan_id' => $jenisTagihan->id, 'jumlah' => 900000]);

    return [$lembaga, $pendaftaran, $tagihan];
}

it('denies membuat skema cicilan without the cicilan.kelola permission', function () {
    [$lembaga, , $tagihan] = siapkanTagihanDaftarUlangBisaDicicil();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($user)->post(route('admin.tagihan.skema-cicilan.store', $tagihan), ['jumlah_termin' => 3])
        ->assertForbidden();
});

it('lets admin_keuangan create a skema cicilan for a tagihan', function () {
    [$lembaga, , $tagihan] = siapkanTagihanDaftarUlangBisaDicicil();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->post(route('admin.tagihan.skema-cicilan.store', $tagihan), ['jumlah_termin' => 3]);

    $response->assertRedirect();
    expect(SkemaCicilan::where('tagihan_id', $tagihan->id)->first()->dibuat_oleh)->toBe('admin');
    expect(Cicilan::where('skema_cicilan_id', SkemaCicilan::where('tagihan_id', $tagihan->id)->value('id'))->count())->toBe(3);
});

it('404s creating a skema cicilan for a tagihan belonging to a different lembaga', function () {
    [, , $tagihanLembagaLain] = siapkanTagihanDaftarUlangBisaDicicil();
    $lembagaSaya = \App\Models\Lembaga::factory()->create();
    $user = User::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $user->assignRole('admin_keuangan');

    $this->actingAs($user)->post(route('admin.tagihan.skema-cicilan.store', $tagihanLembagaLain), ['jumlah_termin' => 3])
        ->assertNotFound();
});

it('lets admin_keuangan edit nominal manually and rejects a mismatched total', function () {
    [$lembaga, , $tagihan] = siapkanTagihanDaftarUlangBisaDicicil();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');
    $this->actingAs($user)->post(route('admin.tagihan.skema-cicilan.store', $tagihan), ['jumlah_termin' => 3]);
    $skema = SkemaCicilan::where('tagihan_id', $tagihan->id)->first();

    $responseSalah = $this->actingAs($user)->post(route('admin.skema-cicilan.nominal.store', $skema), [
        'nominal' => [1 => 100000, 2 => 300000, 3 => 300000],
    ]);
    $responseSalah->assertSessionHasErrors();

    $responseBenar = $this->actingAs($user)->post(route('admin.skema-cicilan.nominal.store', $skema), [
        'nominal' => [1 => 500000, 2 => 200000, 3 => 200000],
    ]);
    $responseBenar->assertRedirect();
    expect((int) Cicilan::where('skema_cicilan_id', $skema->id)->where('urutan', 1)->value('nominal'))->toBe(500000);
});
