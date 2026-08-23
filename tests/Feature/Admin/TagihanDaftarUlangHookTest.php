<?php
// tests/Feature/Admin/TagihanDaftarUlangHookTest.php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\NominalTagihanJalur;
use App\Domains\Keuangan\Models\Tagihan;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('generates a tagihan daftar ulang automatically when a pendaftaran is marked diterima', function () {
    [$lembaga, $jalur, , $pendaftaran] = buatPendaftaranUntukAdmin(status: 'menunggu_verifikasi');
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Uang Pangkal', 'kategori' => 'daftar_ulang', 'bisa_dicicil' => true, 'maks_cicilan' => 3]);
    NominalTagihanJalur::create(['jenis_tagihan_id' => $jenisTagihan->id, 'jalur_ppdb_id' => $jalur->id, 'nominal' => 3000000]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('kepala_sekolah');

    $this->actingAs($user)->post(route('admin.spmb-pendaftaran.keputusan', $pendaftaran), ['status' => 'diterima']);

    $tagihan = Tagihan::where('pendaftaran_id', $pendaftaran->id)->where('kategori', 'daftar_ulang')->first();
    expect($tagihan)->not->toBeNull();
    expect((float) $tagihan->total_tagihan)->toBe(3000000.0);
});

it('does not generate a tagihan daftar ulang when the decision is ditolak', function () {
    [$lembaga, $jalur, , $pendaftaran] = buatPendaftaranUntukAdmin(status: 'menunggu_verifikasi');
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Uang Pangkal', 'kategori' => 'daftar_ulang', 'bisa_dicicil' => true, 'maks_cicilan' => 3]);
    NominalTagihanJalur::create(['jenis_tagihan_id' => $jenisTagihan->id, 'jalur_ppdb_id' => $jalur->id, 'nominal' => 3000000]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('kepala_sekolah');

    $this->actingAs($user)->post(route('admin.spmb-pendaftaran.keputusan', $pendaftaran), ['status' => 'ditolak']);

    $this->assertDatabaseMissing('tagihan', ['pendaftaran_id' => $pendaftaran->id]);
});

it('still saves the keputusan successfully even when no tagihan daftar ulang can be generated', function () {
    [$lembaga, , , $pendaftaran] = buatPendaftaranUntukAdmin(status: 'menunggu_verifikasi');
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('kepala_sekolah');

    $response = $this->actingAs($user)->post(route('admin.spmb-pendaftaran.keputusan', $pendaftaran), ['status' => 'diterima']);

    $response->assertOk();
    expect($pendaftaran->fresh()->status)->toBe('diterima');
});
