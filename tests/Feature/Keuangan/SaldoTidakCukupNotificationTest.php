<?php

use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\SystemSetting;
use App\Models\Tagihan;
use App\Notifications\Finance\SaldoTidakCukupNotification;
use Illuminate\Support\Facades\Notification;

it('sends SaldoTidakCukupNotification for a tagihan that gets fully skipped due to insufficient balance', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $orangTua = OrangTua::factory()->create();
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);
    SystemSetting::create(['lembaga_id' => $lembaga->id, 'key' => 'auto_debit_enabled', 'value' => 'true']);

    // Two tagihan: the higher-priority (lower priority_score, processed first) one is
    // small enough to fully consume the wallet balance by itself, leaving saldo == 0
    // by the time the loop reaches the second (more expensive) tagihan. Per
    // AutoAllocationEngine's guarantee that every tagihan actually iterated is fully
    // entered into $allocated (the belum_bayar/sebagian filter means net_amount >
    // paid_amount always), a "skip" can only happen when the loop `break`s before
    // reaching a tagihan — i.e. saldo is already exhausted by an earlier one.
    $jenisTagihanTinggi = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'priority_score' => 1]);
    Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihanTinggi->id,
        'net_amount' => 100000, 'paid_amount' => 0, 'status' => 'belum_bayar',
    ]);

    $jenisTagihanRendah = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'priority_score' => 2]);
    $tagihanMahal = Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihanRendah->id,
        'net_amount' => 500000, 'paid_amount' => 0, 'status' => 'belum_bayar',
    ]);

    $wallet = $siswa->wallet;
    $wallet->topup(100000); // habis dipakai tagihan pertama, tagihan kedua ter-skip sepenuhnya

    Notification::assertSentTo($orangTua, SaldoTidakCukupNotification::class, function ($notification) use ($tagihanMahal) {
        return $notification->tagihan->id === $tagihanMahal->id;
    });
});

it('only sends SaldoTidakCukupNotification for the highest-priority skipped tagihan when multiple are skipped', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $orangTua = OrangTua::factory()->create();
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);
    SystemSetting::create(['lembaga_id' => $lembaga->id, 'key' => 'auto_debit_enabled', 'value' => 'true']);

    // Three tagihan ordered by priority_score. The wallet only covers the first
    // (highest priority) one, so the second and third are both skipped. Per the
    // spec (Addendum A), only the FIRST skipped tagihan (highest priority among the
    // skipped ones) should trigger a notification — not both.
    $jenisTagihanTinggi = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'priority_score' => 1]);
    Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihanTinggi->id,
        'net_amount' => 100000, 'paid_amount' => 0, 'status' => 'belum_bayar',
    ]);

    $jenisTagihanMenengah = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'priority_score' => 2]);
    $tagihanMenengah = Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihanMenengah->id,
        'net_amount' => 300000, 'paid_amount' => 0, 'status' => 'belum_bayar',
    ]);

    $jenisTagihanRendah = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'priority_score' => 3]);
    $tagihanRendah = Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihanRendah->id,
        'net_amount' => 500000, 'paid_amount' => 0, 'status' => 'belum_bayar',
    ]);

    $wallet = $siswa->wallet;
    $wallet->topup(100000); // habis dipakai tagihan pertama, dua tagihan lainnya ter-skip

    Notification::assertSentTo($orangTua, SaldoTidakCukupNotification::class, function ($notification) use ($tagihanMenengah) {
        return $notification->tagihan->id === $tagihanMenengah->id;
    });

    Notification::assertSentToTimes($orangTua, SaldoTidakCukupNotification::class, 1);
});

it('does not send when the wallet balance fully covers every active tagihan', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $orangTua = OrangTua::factory()->create();
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);
    SystemSetting::create(['lembaga_id' => $lembaga->id, 'key' => 'auto_debit_enabled', 'value' => 'true']);

    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id,
        'net_amount' => 50000, 'paid_amount' => 0, 'status' => 'belum_bayar',
    ]);

    $wallet = $siswa->wallet;
    $wallet->topup(100000); // lebih dari cukup

    Notification::assertNotSentTo($orangTua, SaldoTidakCukupNotification::class);
});

it('is urgent = false', function () {
    $notification = new SaldoTidakCukupNotification(\App\Models\Tagihan::factory()->make(), 100000.0);
    expect($notification->isUrgent())->toBeFalse();
});
