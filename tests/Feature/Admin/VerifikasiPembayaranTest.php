<?php
// tests/Feature/Admin/VerifikasiPembayaranTest.php

use App\Models\Pembayaran;
use App\Models\Tagihan;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('denies access to the payment verification queue without pembayaran.view', function () {
    [$lembaga] = buatPendaftaranUntukAdmin();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($user)->get(route('admin.pembayaran.index'))->assertForbidden();
    $this->actingAs($user)->getJson(route('admin.pembayaran.data'))->assertForbidden();
});

it('only lists pending payments belonging to the acting user own lembaga', function () {
    [$lembagaA, , , $pendaftaranA] = buatPendaftaranUntukAdmin(namaCalon: 'Milik A', status: 'diterima');
    [, , , $pendaftaranB] = buatPendaftaranUntukAdmin(namaCalon: 'Milik B', status: 'diterima');
    $tagihanA = Tagihan::create(['pendaftaran_id' => $pendaftaranA->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => 500000, 'status' => 'belum_bayar']);
    $tagihanB = Tagihan::create(['pendaftaran_id' => $pendaftaranB->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => 500000, 'status' => 'belum_bayar']);
    Pembayaran::create(['tagihan_id' => $tagihanA->id, 'sumber' => 'calon_siswa', 'metode' => 'transfer_manual', 'status' => 'menunggu_verifikasi']);
    Pembayaran::create(['tagihan_id' => $tagihanB->id, 'sumber' => 'calon_siswa', 'metode' => 'transfer_manual', 'status' => 'menunggu_verifikasi']);
    $user = User::factory()->create(['lembaga_id' => $lembagaA->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->getJson(route('admin.pembayaran.data'));

    $names = collect($response->json('data'))->pluck('nama_calon_murid');
    expect($names)->toContain('Milik A');
    expect($names)->not->toContain('Milik B');
});

it('lets admin_keuangan approve a pending payment', function () {
    [$lembaga, , , $pendaftaran] = buatPendaftaranUntukAdmin(status: 'diterima');
    $tagihan = Tagihan::create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => 500000, 'status' => 'belum_bayar']);
    $pembayaran = Pembayaran::create(['tagihan_id' => $tagihan->id, 'sumber' => 'calon_siswa', 'metode' => 'transfer_manual', 'status' => 'menunggu_verifikasi']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->post(route('admin.pembayaran.verifikasi', $pembayaran), ['keputusan' => 'lunas']);

    $response->assertRedirect();
    expect($pembayaran->fresh()->status)->toBe('lunas');
    expect($tagihan->fresh()->status)->toBe('lunas');
});

it('requires catatan when rejecting a pending payment', function () {
    [$lembaga, , , $pendaftaran] = buatPendaftaranUntukAdmin(status: 'diterima');
    $tagihan = Tagihan::create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => 500000, 'status' => 'belum_bayar']);
    $pembayaran = Pembayaran::create(['tagihan_id' => $tagihan->id, 'sumber' => 'calon_siswa', 'metode' => 'transfer_manual', 'status' => 'menunggu_verifikasi']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->post(route('admin.pembayaran.verifikasi', $pembayaran), ['keputusan' => 'ditolak']);

    $response->assertSessionHasErrors('catatan_verifikasi');
    expect($pembayaran->fresh()->status)->toBe('menunggu_verifikasi');
});

it('404s verifying a payment belonging to a pendaftaran in a different lembaga', function () {
    [, , , $pendaftaranLain] = buatPendaftaranUntukAdmin(status: 'diterima');
    $tagihanLain = Tagihan::create(['pendaftaran_id' => $pendaftaranLain->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => 500000, 'status' => 'belum_bayar']);
    $pembayaranLain = Pembayaran::create(['tagihan_id' => $tagihanLain->id, 'sumber' => 'calon_siswa', 'metode' => 'transfer_manual', 'status' => 'menunggu_verifikasi']);
    $lembagaSaya = \App\Models\Lembaga::factory()->create();
    $user = User::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $user->assignRole('admin_keuangan');

    $this->actingAs($user)->post(route('admin.pembayaran.verifikasi', $pembayaranLain), ['keputusan' => 'lunas'])
        ->assertNotFound();
});
