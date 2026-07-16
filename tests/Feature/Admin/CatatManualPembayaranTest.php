<?php
// tests/Feature/Admin/CatatManualPembayaranTest.php

use App\Models\Cicilan;
use App\Models\Pembayaran;
use App\Models\Tagihan;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('denies catat manual without the pembayaran.catat-manual permission', function () {
    [$lembaga, , , $pendaftaran] = buatPendaftaranUntukAdmin(status: 'diterima');
    $tagihan = Tagihan::create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => 500000, 'status' => 'belum_bayar']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($user)->post(route('admin.tagihan.catat-manual', $tagihan))->assertForbidden();
});

it('lets admin_keuangan record a lump-sum tagihan payment directly as lunas', function () {
    [$lembaga, , , $pendaftaran] = buatPendaftaranUntukAdmin(status: 'diterima');
    $tagihan = Tagihan::create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => 500000, 'status' => 'belum_bayar']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->post(route('admin.tagihan.catat-manual', $tagihan));

    $response->assertRedirect();
    expect($tagihan->fresh()->status)->toBe('lunas');
    expect(Pembayaran::where('tagihan_id', $tagihan->id)->first()->sumber)->toBe('admin');
});
