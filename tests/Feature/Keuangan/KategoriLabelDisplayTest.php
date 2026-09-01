<?php

// tests/Feature/Keuangan/KategoriLabelDisplayTest.php

use App\Domains\Keuangan\Models\Pembayaran;
use App\Domains\Keuangan\Models\Tagihan;
use App\Models\AkunPendaftar;
use App\Models\CalonMurid;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Both PembayaranController::labelJenis() and portal/tagihan/index.blade.php
 * used to derive their label from `$kategori === 'pendaftaran' ? 'Tagihan
 * Pendaftaran' : 'Tagihan Daftar Ulang'` — a binary ternary that is wrong for
 * any other kategori, and (post-enum-cast) always false, always falling to
 * "Tagihan Daftar Ulang" regardless of the real kategori. Using
 * kategori=spp here proves the fix (KategoriTagihan::label()) is genuinely
 * correct per kategori, not just a relabelled binary ternary.
 */
it('PembayaranController shows the correct kategori label for a non-PPDB tagihan, not "Daftar Ulang"', function () {
    (new RolePermissionSeeder)->run();
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $calonMurid = CalonMurid::factory()->create(['yayasan_id' => $yayasan->id]);
    $pendaftaran = Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'calon_murid_id' => $calonMurid->id, 'status' => 'diterima']);
    $tagihan = Tagihan::factory()->create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'spp', 'total_tagihan' => 250000, 'status' => 'belum_bayar']);
    Pembayaran::create(['tagihan_id' => $tagihan->id, 'sumber' => 'calon_siswa', 'metode' => 'transfer_manual', 'status' => 'menunggu_verifikasi']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('bendahara_lembaga');

    $response = $this->actingAs($user)->getJson(route('admin.pembayaran.data'));

    $jenis = collect($response->json('data'))->pluck('jenis');
    expect($jenis)->toContain('SPP — Rp 250.000');
    expect($jenis)->not->toContain('Tagihan Daftar Ulang — Rp 250.000');
});

it('portal/tagihan/index shows the correct kategori label for a non-PPDB tagihan, not "Daftar Ulang"', function () {
    $akun = AkunPendaftar::factory()->create();
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $calonMurid = CalonMurid::factory()->create(['yayasan_id' => $yayasan->id]);
    $pendaftaran = Pendaftaran::factory()->create([
        'lembaga_id' => $lembaga->id, 'calon_murid_id' => $calonMurid->id,
        'akun_pendaftar_id' => $akun->id, 'status' => 'diterima',
    ]);
    Tagihan::factory()->create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'spp', 'total_tagihan' => 250000, 'status' => 'belum_bayar']);

    $response = $this->actingAs($akun, 'portal')->get(route('portal.tagihan.index'));

    $response->assertOk();
    $response->assertSee('SPP');
    $response->assertDontSee('Tagihan Daftar Ulang');
});
