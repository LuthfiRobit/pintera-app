<?php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Pembayaran;
use App\Models\PembayaranTagihan;
use App\Models\Siswa;
use App\Domains\Keuangan\Models\Tagihan;
use App\Notifications\Finance\PembayaranBerhasilNotification;
use App\Services\Finance\PaymentAllocationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

it('sends PembayaranBerhasilNotification when a tagihan transitions to lunas', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $orangTua = OrangTua::factory()->create();
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    $tagihan = Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id,
        'net_amount' => 100000, 'paid_amount' => 0, 'status' => 'belum_bayar',
    ]);
    $pembayaran = Pembayaran::factory()->create(['siswa_id' => $siswa->id, 'metode' => 'cash', 'status' => 'lunas']);
    PembayaranTagihan::create(['pembayaran_id' => $pembayaran->id, 'tagihan_id' => $tagihan->id, 'amount_allocated' => 100000]);

    DB::transaction(function () use ($pembayaran) {
        app(PaymentAllocationService::class)->allocate($pembayaran);
    });

    Notification::assertSentTo($orangTua, PembayaranBerhasilNotification::class);
});

it('does not send when the tagihan only becomes sebagian, not lunas', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $orangTua = OrangTua::factory()->create();
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    $tagihan = Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id,
        'net_amount' => 100000, 'paid_amount' => 0, 'status' => 'belum_bayar',
    ]);
    $pembayaran = Pembayaran::factory()->create(['siswa_id' => $siswa->id, 'metode' => 'cash', 'status' => 'lunas']);
    PembayaranTagihan::create(['pembayaran_id' => $pembayaran->id, 'tagihan_id' => $tagihan->id, 'amount_allocated' => 50000]);

    DB::transaction(function () use ($pembayaran) {
        app(PaymentAllocationService::class)->allocate($pembayaran);
    });

    Notification::assertNothingSent();
});

it('does not send twice if allocate() is somehow called again on an already-lunas tagihan', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $orangTua = OrangTua::factory()->create();
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    $tagihan = Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id,
        'net_amount' => 100000, 'paid_amount' => 0, 'status' => 'belum_bayar',
    ]);
    $pembayaran = Pembayaran::factory()->create(['siswa_id' => $siswa->id, 'metode' => 'cash', 'status' => 'lunas']);
    PembayaranTagihan::create(['pembayaran_id' => $pembayaran->id, 'tagihan_id' => $tagihan->id, 'amount_allocated' => 100000]);

    DB::transaction(fn () => app(PaymentAllocationService::class)->allocate($pembayaran));
    Notification::fake(); // reset, only capture the SECOND call
    DB::transaction(fn () => app(PaymentAllocationService::class)->allocate($pembayaran));

    Notification::assertNothingSent();
});
