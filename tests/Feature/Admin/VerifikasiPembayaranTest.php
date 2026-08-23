<?php
// tests/Feature/Admin/VerifikasiPembayaranTest.php

use App\Models\Lembaga;
use App\Models\Pembayaran;
use App\Domains\Keuangan\Models\Tagihan;
use App\Models\User;
use App\Services\PembayaranService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

/**
 * Builds a Pembayaran reachable ONLY via cicilan_id (tagihan_id null) — the
 * skema-cicilan ownership path — as opposed to the direct tagihan_id path
 * every other test in this file exercises. Returns [Lembaga, Pembayaran].
 */
function buatPembayaranViaCicilan(?Lembaga $lembaga = null, string $namaCalon = 'Ahmad Fauzan'): array
{
    [$lembagaAktual, , , $pendaftaran] = buatPendaftaranUntukAdmin($lembaga, namaCalon: $namaCalon, status: 'diterima');
    $tagihan = Tagihan::create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => 900000, 'status' => 'belum_bayar']);
    $skema = app(PembayaranService::class)->buatSkemaCicilan($tagihan, 3, 'calon_siswa');
    $termin1 = $skema->cicilan()->where('urutan', 1)->first();
    $pembayaran = Pembayaran::create(['cicilan_id' => $termin1->id, 'sumber' => 'calon_siswa', 'metode' => 'transfer_manual', 'status' => 'menunggu_verifikasi']);

    return [$lembagaAktual, $pembayaran];
}

it('labels two pending payments for the same candidate distinguishably by kategori and nominal', function () {
    [$lembaga, , , $pendaftaran] = buatPendaftaranUntukAdmin(status: 'diterima');
    $tagihanPendaftaran = Tagihan::create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'pendaftaran', 'total_tagihan' => 150000, 'status' => 'belum_bayar']);
    $tagihanDaftarUlang = Tagihan::create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => 900000, 'status' => 'belum_bayar']);
    Pembayaran::create(['tagihan_id' => $tagihanPendaftaran->id, 'sumber' => 'calon_siswa', 'metode' => 'transfer_manual', 'status' => 'menunggu_verifikasi']);
    Pembayaran::create(['tagihan_id' => $tagihanDaftarUlang->id, 'sumber' => 'calon_siswa', 'metode' => 'transfer_manual', 'status' => 'menunggu_verifikasi']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->getJson(route('admin.pembayaran.data'));

    $jenis = collect($response->json('data'))->pluck('jenis');
    expect($jenis)->toContain('Tagihan Pendaftaran — Rp 150.000');
    expect($jenis)->toContain('Tagihan Daftar Ulang — Rp 900.000');
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

it('lists a payment reachable only via the cicilan ownership path, scoped to the acting user own lembaga', function () {
    [$lembagaA, $pembayaranA] = buatPembayaranViaCicilan(namaCalon: 'Cicilan Milik A');
    [, $pembayaranB] = buatPembayaranViaCicilan(namaCalon: 'Cicilan Milik B');
    $user = User::factory()->create(['lembaga_id' => $lembagaA->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->getJson(route('admin.pembayaran.data'));

    $names = collect($response->json('data'))->pluck('nama_calon_murid');
    expect($names)->toContain('Cicilan Milik A');
    expect($names)->not->toContain('Cicilan Milik B');
    expect($pembayaranA->tagihan_id)->toBeNull();
    expect($pembayaranA->cicilan_id)->not->toBeNull();
});

it('404s verifying a cicilan-reachable payment belonging to a pendaftaran in a different lembaga', function () {
    [, $pembayaranLain] = buatPembayaranViaCicilan();
    $lembagaSaya = Lembaga::factory()->create();
    $user = User::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $user->assignRole('admin_keuangan');

    $this->actingAs($user)->post(route('admin.pembayaran.verifikasi', $pembayaranLain), ['keputusan' => 'lunas'])
        ->assertNotFound();
});

it('lets admin_keuangan approve a cicilan-reachable pending payment', function () {
    [$lembaga, $pembayaran] = buatPembayaranViaCicilan();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->post(route('admin.pembayaran.verifikasi', $pembayaran), ['keputusan' => 'lunas']);

    $response->assertRedirect();
    expect($pembayaran->fresh()->status)->toBe('lunas');
});

it('renders Terima/Tolak verification controls and the bukti transfer link on the pendaftaran detail page for a pending lump-sum payment', function () {
    [$lembaga, , , $pendaftaran] = buatPendaftaranUntukAdmin(status: 'diterima');
    $tagihan = Tagihan::create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => 500000, 'status' => 'belum_bayar']);
    $pembayaran = Pembayaran::create([
        'tagihan_id' => $tagihan->id, 'sumber' => 'calon_siswa', 'metode' => 'transfer_manual',
        'file_path' => 'bukti-transfer/contoh.pdf', 'status' => 'menunggu_verifikasi',
    ]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');
    $user->givePermissionTo('spmb-pendaftaran.view');

    $response = $this->actingAs($user)->get(route('admin.spmb-pendaftaran.show', $pendaftaran));

    $response->assertOk();
    $response->assertSee('Terima');
    $response->assertSee('Lihat bukti transfer');
    $response->assertSee(route('admin.pembayaran.verifikasi', $pembayaran), false);
});

it('does not render verification controls for a user without pembayaran.verifikasi permission', function () {
    [$lembaga, , , $pendaftaran] = buatPendaftaranUntukAdmin(status: 'diterima');
    $tagihan = Tagihan::create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => 500000, 'status' => 'belum_bayar']);
    $pembayaran = Pembayaran::create([
        'tagihan_id' => $tagihan->id, 'sumber' => 'calon_siswa', 'metode' => 'transfer_manual',
        'file_path' => 'bukti-transfer/contoh.pdf', 'status' => 'menunggu_verifikasi',
    ]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->givePermissionTo('spmb-pendaftaran.view');

    $response = $this->actingAs($user)->get(route('admin.spmb-pendaftaran.show', $pendaftaran));

    $response->assertOk();
    $response->assertDontSee(route('admin.pembayaran.verifikasi', $pembayaran), false);
});
