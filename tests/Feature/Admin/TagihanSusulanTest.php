<?php
// tests/Feature/Admin/TagihanSusulanTest.php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\NominalTagihanJalur;
use App\Models\Tagihan;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('denies buat tagihan susulan without the tagihan.buat-susulan permission', function () {
    [$lembaga, , , $pendaftaran] = buatPendaftaranUntukAdmin();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($user)->post(route('admin.tagihan.susulan', $pendaftaran), ['kategori' => 'pendaftaran'])->assertForbidden();
});

it('lets admin_keuangan generate a missing tagihan susulan using current nominal', function () {
    [$lembaga, $jalur, , $pendaftaran] = buatPendaftaranUntukAdmin();
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);
    NominalTagihanJalur::create(['jenis_tagihan_id' => $jenisTagihan->id, 'jalur_ppdb_id' => $jalur->id, 'nominal' => 150000]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->post(route('admin.tagihan.susulan', $pendaftaran), ['kategori' => 'pendaftaran']);

    $response->assertRedirect();
    expect(Tagihan::where('pendaftaran_id', $pendaftaran->id)->where('kategori', 'pendaftaran')->exists())->toBeTrue();
});

it('does not create a duplicate tagihan when buat susulan is triggered twice for the same kategori', function () {
    [$lembaga, $jalur, , $pendaftaran] = buatPendaftaranUntukAdmin();
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);
    NominalTagihanJalur::create(['jenis_tagihan_id' => $jenisTagihan->id, 'jalur_ppdb_id' => $jalur->id, 'nominal' => 150000]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $this->actingAs($user)->post(route('admin.tagihan.susulan', $pendaftaran), ['kategori' => 'pendaftaran']);
    $this->actingAs($user)->post(route('admin.tagihan.susulan', $pendaftaran), ['kategori' => 'pendaftaran']);

    expect(Tagihan::where('pendaftaran_id', $pendaftaran->id)->where('kategori', 'pendaftaran')->count())->toBe(1);
});

it('404s when trying to generate a tagihan susulan for a pendaftaran belonging to a different lembaga', function () {
    [, , , $pendaftaranLembagaLain] = buatPendaftaranUntukAdmin();
    $lembagaSaya = \App\Models\Lembaga::factory()->create();
    $user = User::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $user->assignRole('admin_keuangan');

    $this->actingAs($user)->post(route('admin.tagihan.susulan', $pendaftaranLembagaLain), ['kategori' => 'pendaftaran'])
        ->assertNotFound();
});
