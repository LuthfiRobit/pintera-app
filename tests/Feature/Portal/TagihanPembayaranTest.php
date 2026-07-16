<?php
// tests/Feature/Portal/TagihanPembayaranTest.php

use App\Models\AkunPendaftar;
use App\Models\CalonMurid;
use App\Models\Cicilan;
use App\Models\Lembaga;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Models\SkemaCicilan;
use App\Models\Tagihan;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function siapkanTagihanUntukPortal(AkunPendaftar $akun, int $total = 500000, string $kategori = 'daftar_ulang'): Tagihan
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $calonMurid = CalonMurid::factory()->create(['yayasan_id' => $yayasan->id]);
    $pendaftaran = Pendaftaran::factory()->create([
        'lembaga_id' => $lembaga->id, 'calon_murid_id' => $calonMurid->id,
        'akun_pendaftar_id' => $akun->id, 'status' => 'diterima',
    ]);

    return Tagihan::create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => $kategori, 'total_tagihan' => $total, 'status' => 'belum_bayar']);
}

it('shows only tagihan belonging to pendaftaran linked to the logged-in akun', function () {
    $akunSaya = AkunPendaftar::factory()->create();
    $tagihanSaya = siapkanTagihanUntukPortal($akunSaya);
    $akunLain = AkunPendaftar::factory()->create();
    $tagihanLain = siapkanTagihanUntukPortal($akunLain);

    $response = $this->actingAs($akunSaya, 'portal')->get(route('portal.tagihan.index'));

    $response->assertOk();
    $response->assertSee($tagihanSaya->pendaftaran->kode_pendaftaran);
    $response->assertDontSee($tagihanLain->pendaftaran->kode_pendaftaran);
});

it('lets the candidate upload bukti transfer for a lump-sum tagihan, landing as menunggu_verifikasi', function () {
    Storage::fake('public');
    $akun = AkunPendaftar::factory()->create();
    $tagihan = siapkanTagihanUntukPortal($akun);
    $file = UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf');

    $response = $this->actingAs($akun, 'portal')->post(route('portal.tagihan.bayar-lunas', $tagihan), ['bukti' => $file]);

    $response->assertRedirect();
    $pembayaran = Pembayaran::where('tagihan_id', $tagihan->id)->first();
    expect($pembayaran->status)->toBe('menunggu_verifikasi');
    expect($pembayaran->sumber)->toBe('calon_siswa');
    Storage::disk('public')->assertExists($pembayaran->file_path);
});

it('404s uploading bukti transfer for a tagihan belonging to a different akun', function () {
    Storage::fake('public');
    $akunLain = AkunPendaftar::factory()->create();
    $tagihanLain = siapkanTagihanUntukPortal($akunLain);
    $akunSaya = AkunPendaftar::factory()->create();
    $file = UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf');

    $this->actingAs($akunSaya, 'portal')->post(route('portal.tagihan.bayar-lunas', $tagihanLain), ['bukti' => $file])
        ->assertNotFound();
});

it('404s creating a skema cicilan for a tagihan belonging to a different akun', function () {
    $akunLain = AkunPendaftar::factory()->create();
    $tagihanLain = siapkanTagihanUntukPortal($akunLain, 900000);
    \App\Models\JenisTagihan::create(['lembaga_id' => $tagihanLain->pendaftaran->lembaga_id, 'nama' => 'Uang Pangkal', 'kategori' => 'daftar_ulang', 'bisa_dicicil' => true, 'maks_cicilan' => 3]);
    \App\Models\TagihanItem::create(['tagihan_id' => $tagihanLain->id, 'jenis_tagihan_id' => \App\Models\JenisTagihan::first()->id, 'jumlah' => 900000]);
    $akunSaya = AkunPendaftar::factory()->create();

    $this->actingAs($akunSaya, 'portal')->post(route('portal.tagihan.skema-cicilan', $tagihanLain), ['jumlah_termin' => 3])
        ->assertNotFound();

    expect(SkemaCicilan::where('tagihan_id', $tagihanLain->id)->exists())->toBeFalse();
});

it('404s uploading bukti transfer for a cicilan termin belonging to a different akun', function () {
    Storage::fake('public');
    $akunLain = AkunPendaftar::factory()->create();
    $tagihanLain = siapkanTagihanUntukPortal($akunLain, 900000);
    \App\Models\JenisTagihan::create(['lembaga_id' => $tagihanLain->pendaftaran->lembaga_id, 'nama' => 'Uang Pangkal', 'kategori' => 'daftar_ulang', 'bisa_dicicil' => true, 'maks_cicilan' => 3]);
    \App\Models\TagihanItem::create(['tagihan_id' => $tagihanLain->id, 'jenis_tagihan_id' => \App\Models\JenisTagihan::first()->id, 'jumlah' => 900000]);
    $skemaLain = app(\App\Services\PembayaranService::class)->buatSkemaCicilan($tagihanLain, 3, 'calon_siswa');
    $termin1Lain = $skemaLain->cicilan()->where('urutan', 1)->firstOrFail();

    $akunSaya = AkunPendaftar::factory()->create();
    $file = UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf');

    $this->actingAs($akunSaya, 'portal')->post(route('portal.tagihan.bayar-cicilan', $termin1Lain), ['bukti' => $file])
        ->assertNotFound();

    expect(Pembayaran::where('cicilan_id', $termin1Lain->id)->exists())->toBeFalse();
});

it('shows every payment attempt for a tagihan in riwayat, including rejected ones with their catatan, ordered newest first', function () {
    Storage::fake('public');
    $akun = AkunPendaftar::factory()->create();
    $tagihan = siapkanTagihanUntukPortal($akun);
    $admin = \App\Models\User::factory()->create();
    $pertama = Pembayaran::create(['tagihan_id' => $tagihan->id, 'sumber' => 'calon_siswa', 'metode' => 'transfer_manual', 'file_path' => 'bukti/1.pdf', 'status' => 'ditolak', 'catatan_verifikasi' => 'Bukti buram, ulangi', 'diverifikasi_oleh_user_id' => $admin->id, 'diverifikasi_pada' => now()->subDay()]);
    $kedua = Pembayaran::create(['tagihan_id' => $tagihan->id, 'sumber' => 'calon_siswa', 'metode' => 'transfer_manual', 'file_path' => 'bukti/2.pdf', 'status' => 'menunggu_verifikasi']);

    $response = $this->actingAs($akun, 'portal')->get(route('portal.tagihan.index'));

    $response->assertOk();
    $response->assertSeeInOrder(['Menunggu verifikasi', 'Ditolak', 'Bukti buram, ulangi'], false);
});

it('lets the candidate choose cicilan and then upload bukti for the first termin only', function () {
    Storage::fake('public');
    $akun = AkunPendaftar::factory()->create();
    $tagihan = siapkanTagihanUntukPortal($akun, 900000);
    \App\Models\JenisTagihan::create(['lembaga_id' => $tagihan->pendaftaran->lembaga_id, 'nama' => 'Uang Pangkal', 'kategori' => 'daftar_ulang', 'bisa_dicicil' => true, 'maks_cicilan' => 3]);
    \App\Models\TagihanItem::create(['tagihan_id' => $tagihan->id, 'jenis_tagihan_id' => \App\Models\JenisTagihan::first()->id, 'jumlah' => 900000]);

    $this->actingAs($akun, 'portal')->post(route('portal.tagihan.skema-cicilan', $tagihan), ['jumlah_termin' => 3])
        ->assertRedirect();
    $skema = SkemaCicilan::where('tagihan_id', $tagihan->id)->firstOrFail();
    expect($skema->dibuat_oleh)->toBe('calon_siswa');
    $termin1 = $skema->cicilan()->where('urutan', 1)->firstOrFail();
    $termin2 = $skema->cicilan()->where('urutan', 2)->firstOrFail();

    $file = UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf');
    $this->actingAs($akun, 'portal')->post(route('portal.tagihan.bayar-cicilan', $termin1), ['bukti' => $file])
        ->assertRedirect();
    expect(Pembayaran::where('cicilan_id', $termin1->id)->first()->status)->toBe('menunggu_verifikasi');

    $this->actingAs($akun, 'portal')->post(route('portal.tagihan.bayar-cicilan', $termin2), ['bukti' => $file])
        ->assertSessionHasErrors();
});

it('no longer shows the Bayar Lunas form once a lump-sum bukti transfer is pending verification', function () {
    Storage::fake('public');
    $akun = AkunPendaftar::factory()->create();
    $tagihan = siapkanTagihanUntukPortal($akun);

    $response = $this->actingAs($akun, 'portal')->get(route('portal.tagihan.index'));
    $response->assertOk();
    $response->assertSee(route('portal.tagihan.bayar-lunas', $tagihan), false);

    Pembayaran::create([
        'tagihan_id' => $tagihan->id, 'sumber' => 'calon_siswa', 'metode' => 'transfer_manual',
        'file_path' => 'bukti/pending.pdf', 'status' => 'menunggu_verifikasi',
    ]);

    $afterUpload = $this->actingAs($akun, 'portal')->get(route('portal.tagihan.index'));
    $afterUpload->assertOk();
    $afterUpload->assertDontSee(route('portal.tagihan.bayar-lunas', $tagihan), false);
});

it('shows the Kirim Bukti upload form again for a cicilan termin that was rejected', function () {
    Storage::fake('public');
    $akun = AkunPendaftar::factory()->create();
    $tagihan = siapkanTagihanUntukPortal($akun, 900000);
    \App\Models\JenisTagihan::create(['lembaga_id' => $tagihan->pendaftaran->lembaga_id, 'nama' => 'Uang Pangkal', 'kategori' => 'daftar_ulang', 'bisa_dicicil' => true, 'maks_cicilan' => 3]);
    \App\Models\TagihanItem::create(['tagihan_id' => $tagihan->id, 'jenis_tagihan_id' => \App\Models\JenisTagihan::first()->id, 'jumlah' => 900000]);
    $skema = app(\App\Services\PembayaranService::class)->buatSkemaCicilan($tagihan, 3, 'calon_siswa');
    $termin1 = $skema->cicilan()->where('urutan', 1)->firstOrFail();
    $termin1->update(['status' => 'ditolak']);
    Pembayaran::create(['cicilan_id' => $termin1->id, 'sumber' => 'calon_siswa', 'metode' => 'transfer_manual', 'file_path' => 'bukti/1.pdf', 'status' => 'ditolak', 'catatan_verifikasi' => 'Buram']);

    $response = $this->actingAs($akun, 'portal')->get(route('portal.tagihan.index'));

    $response->assertOk();
    $response->assertSee(route('portal.tagihan.bayar-cicilan', $termin1), false);
    $response->assertSee('Kirim Bukti');
});
