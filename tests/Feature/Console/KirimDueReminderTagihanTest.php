<?php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\NotificationLog;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\Tagihan;
use App\Notifications\Finance\DueReminderNotification;
use Illuminate\Support\Facades\Notification;

it('sends a non-urgent reminder for a tagihan due in 3 days, to the kontak utama only', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kontakUtama = OrangTua::factory()->create();
    $siswa->orangTua()->attach($kontakUtama->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);
    $orangTuaLain = OrangTua::factory()->create();
    $siswa->orangTua()->attach($orangTuaLain->id, ['hubungan' => 'ibu', 'is_kontak_utama' => false]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id,
        'status' => 'belum_bayar', 'jatuh_tempo' => now()->addDays(3)->toDateString(),
    ]);

    $this->artisan('billing:kirim-due-reminder')->assertExitCode(0);

    Notification::assertSentTo($kontakUtama, DueReminderNotification::class, fn ($n) => $n->isUrgent() === false);
    Notification::assertNotSentTo($orangTuaLain, DueReminderNotification::class);
});

it('sends an urgent reminder for a tagihan due tomorrow (H-1)', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kontakUtama = OrangTua::factory()->create();
    $siswa->orangTua()->attach($kontakUtama->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id,
        'status' => 'belum_bayar', 'jatuh_tempo' => now()->addDay()->toDateString(),
    ]);

    $this->artisan('billing:kirim-due-reminder')->assertExitCode(0);

    Notification::assertSentTo($kontakUtama, DueReminderNotification::class, fn ($n) => $n->isUrgent() === true);
});

it('does not send a duplicate reminder for the same tagihan on a same-day re-run (idempotency)', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kontakUtama = OrangTua::factory()->create();
    $siswa->orangTua()->attach($kontakUtama->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id,
        'status' => 'belum_bayar', 'jatuh_tempo' => now()->addDays(3)->toDateString(),
    ]);

    $this->artisan('billing:kirim-due-reminder');
    $this->artisan('billing:kirim-due-reminder'); // re-run same day

    Notification::assertSentToTimes($kontakUtama, DueReminderNotification::class, 1);
});

it('does not send for a tagihan that is already lunas or dibatalkan', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kontakUtama = OrangTua::factory()->create();
    $siswa->orangTua()->attach($kontakUtama->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id,
        'status' => 'lunas', 'jatuh_tempo' => now()->addDays(3)->toDateString(),
    ]);

    $this->artisan('billing:kirim-due-reminder');

    Notification::assertNothingSent();
});
