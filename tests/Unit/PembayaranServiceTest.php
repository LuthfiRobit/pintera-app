<?php
// tests/Unit/PembayaranServiceTest.php

use App\Models\Cicilan;
use App\Models\Lembaga;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Domains\Keuangan\Models\SkemaCicilan;
use App\Models\Tagihan;
use App\Models\User;
use App\Services\PembayaranService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function buatTagihanDaftarUlangUntukPembayaran(int $total = 1000000): Tagihan
{
    $lembaga = Lembaga::factory()->create();
    $pendaftaran = Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'status' => 'diterima']);

    return Tagihan::create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => $total, 'status' => 'belum_bayar']);
}

it('splits nominal evenly with the rounding remainder absorbed by the last termin', function () {
    $tagihan = buatTagihanDaftarUlangUntukPembayaran(1000000);

    $skema = app(PembayaranService::class)->buatSkemaCicilan($tagihan, 3, 'calon_siswa');

    $nominal = $skema->cicilan()->orderBy('urutan')->pluck('nominal')->map(fn ($n) => (int) $n)->all();
    expect($nominal)->toBe([333333, 333333, 333334]);
    expect(array_sum($nominal))->toBe(1000000);
    expect($tagihan->fresh()->status)->toBe('dicicil');
});

it('sets tagihan.jatuh_tempo to the last termin due date when a skema is created', function () {
    $tagihan = buatTagihanDaftarUlangUntukPembayaran(900000);

    app(PembayaranService::class)->buatSkemaCicilan($tagihan, 3, 'calon_siswa');

    $terakhir = $tagihan->fresh()->cicilan()->orderByDesc('urutan')->first();
    expect($tagihan->fresh()->jatuh_tempo->toDateString())->toBe($terakhir->jatuh_tempo->toDateString());
});

it('rejects a manual nominal edit whose total no longer matches total_tagihan', function () {
    $tagihan = buatTagihanDaftarUlangUntukPembayaran(900000);
    $skema = app(PembayaranService::class)->buatSkemaCicilan($tagihan, 3, 'calon_siswa');
    $asli = $skema->cicilan()->orderBy('urutan')->pluck('nominal', 'urutan')->all();

    expect(fn () => app(PembayaranService::class)->simpanNominalManual($skema, [1 => 100000, 2 => 300000, 3 => 300000]))
        ->toThrow(InvalidArgumentException::class);

    expect($skema->cicilan()->orderBy('urutan')->pluck('nominal', 'urutan')->all())->toEqual($asli);
});

it('accepts a manual nominal edit whose total matches exactly, even with an uneven split like a dp', function () {
    $tagihan = buatTagihanDaftarUlangUntukPembayaran(900000);
    $skema = app(PembayaranService::class)->buatSkemaCicilan($tagihan, 3, 'calon_siswa');

    app(PembayaranService::class)->simpanNominalManual($skema, [1 => 500000, 2 => 200000, 3 => 200000]);

    expect((int) $skema->cicilan()->where('urutan', 1)->value('nominal'))->toBe(500000);
});

it('rejects a manual nominal edit that omits a termin, even if the submitted subset sums to total_tagihan', function () {
    $tagihan = buatTagihanDaftarUlangUntukPembayaran(900000);
    $skema = app(PembayaranService::class)->buatSkemaCicilan($tagihan, 3, 'calon_siswa');
    $nominalTermin3Semula = (int) $skema->cicilan()->where('urutan', 3)->value('nominal');

    expect(fn () => app(PembayaranService::class)->simpanNominalManual($skema, [1 => 400000, 2 => 500000]))
        ->toThrow(InvalidArgumentException::class);

    expect((int) $skema->cicilan()->where('urutan', 3)->value('nominal'))->toBe($nominalTermin3Semula);
});

it('records a self-reported payment as menunggu_verifikasi, never lunas immediately', function () {
    $tagihan = buatTagihanDaftarUlangUntukPembayaran(500000);

    $pembayaran = app(PembayaranService::class)->catatPembayaran($tagihan, null, 'calon_siswa', 'bukti/x.pdf', null);

    expect($pembayaran->status)->toBe('menunggu_verifikasi');
    expect($tagihan->fresh()->status)->toBe('belum_bayar');
});

it('records an admin-recorded payment as immediately lunas, updating tagihan.status in the same call', function () {
    $tagihan = buatTagihanDaftarUlangUntukPembayaran(500000);
    $admin = User::factory()->create();

    $pembayaran = app(PembayaranService::class)->catatPembayaran($tagihan, null, 'admin', null, $admin->id);

    expect($pembayaran->status)->toBe('lunas');
    expect($pembayaran->diverifikasi_oleh_user_id)->toBe($admin->id);
    expect($tagihan->fresh()->status)->toBe('lunas');
});

it('never inserts a new payment attempt while one is already pending or already lunas for the same target', function () {
    $tagihan = buatTagihanDaftarUlangUntukPembayaran(500000);
    app(PembayaranService::class)->catatPembayaran($tagihan, null, 'calon_siswa', 'bukti/1.pdf', null);

    expect(fn () => app(PembayaranService::class)->catatPembayaran($tagihan, null, 'calon_siswa', 'bukti/2.pdf', null))
        ->toThrow(RuntimeException::class);
});

it('allows a new payment attempt after the previous one for the same target was rejected, keeping the rejected row untouched', function () {
    $tagihan = buatTagihanDaftarUlangUntukPembayaran(500000);
    $admin = User::factory()->create();
    $pertama = app(PembayaranService::class)->catatPembayaran($tagihan, null, 'calon_siswa', 'bukti/1.pdf', null);
    app(PembayaranService::class)->verifikasiPembayaran($pertama, 'ditolak', 'Bukti buram', $admin->id);

    $kedua = app(PembayaranService::class)->catatPembayaran($tagihan, null, 'calon_siswa', 'bukti/2.pdf', null);

    expect(Pembayaran::where('tagihan_id', $tagihan->id)->count())->toBe(2);
    $pertama->refresh();
    expect($pertama->status)->toBe('ditolak');
    expect($pertama->file_path)->toBe('bukti/1.pdf');
    expect($pertama->catatan_verifikasi)->toBe('Bukti buram');
    expect($kedua->status)->toBe('menunggu_verifikasi');
});

it('rejects paying termin 2 before termin 1 is lunas', function () {
    $tagihan = buatTagihanDaftarUlangUntukPembayaran(900000);
    $skema = app(PembayaranService::class)->buatSkemaCicilan($tagihan, 3, 'calon_siswa');
    $termin2 = $skema->cicilan()->where('urutan', 2)->first();

    expect(fn () => app(PembayaranService::class)->catatPembayaran(null, $termin2, 'calon_siswa', 'bukti/x.pdf', null))
        ->toThrow(RuntimeException::class);
});

it('allows paying termin 2 once termin 1 is verified lunas, and marks the whole tagihan lunas once every termin is paid', function () {
    $tagihan = buatTagihanDaftarUlangUntukPembayaran(900000);
    $service = app(PembayaranService::class);
    $skema = $service->buatSkemaCicilan($tagihan, 3, 'calon_siswa');
    $admin = User::factory()->create();
    [$t1, $t2, $t3] = $skema->cicilan()->orderBy('urutan')->get();

    $service->catatPembayaran(null, $t1, 'admin', null, $admin->id);
    $service->catatPembayaran(null, $t2, 'admin', null, $admin->id);
    expect($tagihan->fresh()->status)->toBe('dicicil');

    $service->catatPembayaran(null, $t3, 'admin', null, $admin->id);
    expect($tagihan->fresh()->status)->toBe('lunas');
});

it('rejecting a cicilan payment sets the termin status to ditolak, not back to belum_bayar', function () {
    $tagihan = buatTagihanDaftarUlangUntukPembayaran(900000);
    $service = app(PembayaranService::class);
    $skema = $service->buatSkemaCicilan($tagihan, 3, 'calon_siswa');
    $admin = User::factory()->create();
    $t1 = $skema->cicilan()->where('urutan', 1)->first();
    $pembayaran = $service->catatPembayaran(null, $t1, 'calon_siswa', 'bukti/x.pdf', null);

    $service->verifikasiPembayaran($pembayaran, 'ditolak', 'Nominal tidak sesuai', $admin->id);

    expect($t1->fresh()->status)->toBe('ditolak');
});
