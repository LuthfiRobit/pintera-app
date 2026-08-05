<?php
// tests/Feature/Console/KirimReminderSesiTest.php

use App\Models\Guru;
use App\Models\Kasus;
use App\Models\KasusSesi;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use App\Notifications\SesiReminderNotification;
use Illuminate\Support\Facades\Notification;

it('sends a reminder for sesi terjadwal tomorrow, but not for other dates or statuses', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);

    $orangTuaUser = User::factory()->create(['lembaga_id' => null]);
    Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $orangTuaUser->assignRole('orang_tua');
    $orangTua = OrangTua::create([
        'user_id' => $orangTuaUser->id, 'nama_lengkap' => 'Ibu Reminder',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200006666',
        'email' => 'ortu.reminder@example.test',
    ]);
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);

    $kasus = Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id,
        'kategori_masalah' => 'Perilaku', 'deskripsi' => 'Contoh.',
    ]);

    $besok = KasusSesi::factory()->create([
        'kasus_id' => $kasus->id, 'peserta' => 'orang_tua',
        'dijadwalkan_pada' => now()->addDay()->setTime(9, 0), 'status' => 'terjadwal',
    ]);
    $lusa = KasusSesi::factory()->create([
        'kasus_id' => $kasus->id, 'peserta' => 'orang_tua',
        'dijadwalkan_pada' => now()->addDays(2)->setTime(9, 0), 'status' => 'terjadwal',
    ]);
    $besokTapiBatal = KasusSesi::factory()->create([
        'kasus_id' => $kasus->id, 'peserta' => 'orang_tua',
        'dijadwalkan_pada' => now()->addDay()->setTime(14, 0), 'status' => 'batal',
    ]);

    Notification::fake();

    $this->artisan('kasus:kirim-reminder-sesi')->assertExitCode(0);

    Notification::assertSentTo($orangTua, SesiReminderNotification::class, fn ($n) => $n->sesi->id === $besok->id);
    Notification::assertSentTimes(SesiReminderNotification::class, 1);
});

it('sends the H-1 reminder via whatsapp to the orang tua kontak utama', function () {
    \Illuminate\Support\Facades\Http::fake(['api.fonnte.com/*' => \Illuminate\Support\Facades\Http::response(['status' => true], 200)]);
    \App\Models\WhatsAppTemplate::firstOrCreate(
        ['kode' => 'reminder_sesi_h1'],
        ['isi_template' => 'Sesi {nama_siswa} besok di {lokasi_sesi} jam {tanggal_sesi}.']
    );

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Ani Wijaya']);

    $orangTuaUser = User::factory()->create(['lembaga_id' => null]);
    Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $orangTuaUser->assignRole('orang_tua');
    $orangTua = OrangTua::create([
        'user_id' => $orangTuaUser->id, 'nama_lengkap' => 'Ibu Reminder Whatsapp',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200007700',
        'email' => 'ortu.reminder.wa@example.test',
    ]);
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);

    $kasus = Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id,
        'kategori_masalah' => 'Perilaku', 'deskripsi' => 'Contoh.',
    ]);

    KasusSesi::factory()->create([
        'kasus_id' => $kasus->id, 'peserta' => 'orang_tua',
        'dijadwalkan_pada' => now()->addDay()->setTime(9, 0), 'status' => 'terjadwal',
        'lokasi_mode' => 'Ruang BK',
    ]);

    $this->artisan('kasus:kirim-reminder-sesi');

    \Illuminate\Support\Facades\Http::assertSent(function ($request) {
        return $request->url() === 'https://api.fonnte.com/send'
            && $request['target'] === '081200007700'
            && str_contains($request['message'], 'Ani Wijaya')
            && str_contains($request['message'], 'Ruang BK');
    });
});
