<?php
// tests/Feature/Admin/TagihanIndexTest.php

use App\Models\JenisTagihan;
use App\Models\Tagihan;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('denies access to the tagihan list without the tagihan.view permission', function () {
    [$lembaga] = buatPendaftaranUntukAdmin();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($user)->get(route('admin.tagihan.index'))->assertForbidden();
    $this->actingAs($user)->getJson(route('admin.tagihan.data'))->assertForbidden();
});

it('shows the index page with the view permission', function () {
    [$lembaga] = buatPendaftaranUntukAdmin();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $this->actingAs($user)->get(route('admin.tagihan.index'))->assertOk();
});

it('returns only tagihan belonging to the acting user own lembaga, via the linked pendaftaran', function () {
    [$lembagaA, $jalurA, , $pendaftaranA] = buatPendaftaranUntukAdmin(namaCalon: 'Milik A');
    [$lembagaB, $jalurB, , $pendaftaranB] = buatPendaftaranUntukAdmin(namaCalon: 'Milik B');
    $jenisTagihanA = JenisTagihan::create(['lembaga_id' => $lembagaA->id, 'nama' => 'Biaya A', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);
    $jenisTagihanB = JenisTagihan::create(['lembaga_id' => $lembagaB->id, 'nama' => 'Biaya B', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);
    Tagihan::create(['pendaftaran_id' => $pendaftaranA->id, 'kategori' => 'pendaftaran', 'total_tagihan' => 100000, 'status' => 'belum_bayar']);
    Tagihan::create(['pendaftaran_id' => $pendaftaranB->id, 'kategori' => 'pendaftaran', 'total_tagihan' => 200000, 'status' => 'belum_bayar']);
    $user = User::factory()->create(['lembaga_id' => $lembagaA->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->getJson(route('admin.tagihan.data'));

    $names = collect($response->json('data'))->pluck('nama_calon_murid');
    expect($names)->toContain('Milik A');
    expect($names)->not->toContain('Milik B');
});

it('filters by search on candidate name or kode pendaftaran', function () {
    [$lembaga, , , $pendaftaranAhmad] = buatPendaftaranUntukAdmin(namaCalon: 'Ahmad Fauzan');
    [, , , $pendaftaranBudi] = buatPendaftaranUntukAdmin($lembaga, 'Budi Santoso');
    Tagihan::create(['pendaftaran_id' => $pendaftaranAhmad->id, 'kategori' => 'pendaftaran', 'total_tagihan' => 100000, 'status' => 'belum_bayar']);
    Tagihan::create(['pendaftaran_id' => $pendaftaranBudi->id, 'kategori' => 'pendaftaran', 'total_tagihan' => 100000, 'status' => 'belum_bayar']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $responseNama = $this->actingAs($user)->getJson(route('admin.tagihan.data', ['search' => 'Ahmad']));
    expect(collect($responseNama->json('data'))->pluck('nama_calon_murid'))->toContain('Ahmad Fauzan')
        ->not->toContain('Budi Santoso');

    $kode = $pendaftaranBudi->fresh()->kode_pendaftaran;
    $responseKode = $this->actingAs($user)->getJson(route('admin.tagihan.data', ['search' => $kode]));
    expect(collect($responseKode->json('data'))->pluck('nama_calon_murid'))->toContain('Budi Santoso')
        ->not->toContain('Ahmad Fauzan');

    $responseTidakAda = $this->actingAs($user)->getJson(route('admin.tagihan.data', ['search' => 'Tidak Ada Sama Sekali']));
    expect($responseTidakAda->json('data'))->toBeEmpty();
});

it('filters by status', function () {
    [$lembaga, , , $pendaftaranLunas] = buatPendaftaranUntukAdmin(namaCalon: 'Sudah Lunas');
    [, , , $pendaftaranBelumBayar] = buatPendaftaranUntukAdmin($lembaga, 'Belum Bayar');
    Tagihan::create(['pendaftaran_id' => $pendaftaranLunas->id, 'kategori' => 'pendaftaran', 'total_tagihan' => 0, 'status' => 'lunas']);
    Tagihan::create(['pendaftaran_id' => $pendaftaranBelumBayar->id, 'kategori' => 'pendaftaran', 'total_tagihan' => 100000, 'status' => 'belum_bayar']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->getJson(route('admin.tagihan.data', ['status' => 'lunas']));

    $names = collect($response->json('data'))->pluck('nama_calon_murid');
    expect($names)->toContain('Sudah Lunas');
    expect($names)->not->toContain('Belum Bayar');
});
