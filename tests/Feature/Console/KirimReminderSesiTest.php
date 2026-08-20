<?php
// tests/Feature/Console/KirimReminderSesiTest.php

use App\Models\Guru;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusSesi;
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

it('sends both a siswa-user mail reminder and an orang-tua whatsapp reminder in the same run without one aborting the other', function () {
    // Regression test for the final whole-branch review's Finding 1/2: the old
    // method_exists($notifiable, 'routeNotificationForMail') guard in
    // SesiReminderNotification::toMail() is FALSE for a plain App\Models\User (it relies on
    // the Notifiable trait's generic routeNotificationFor('mail') fallback, not an explicit
    // override), so ->to() was never called for a siswa's User, the Mailable had no
    // recipient, and MailChannel::send() threw LogicException mid-foreach inside
    // KirimReminderSesi::handle() -- with no try/catch, aborting the command and silently
    // skipping every OTHER sesi's reminder in that same run, including orang-tua whatsapp
    // reminders that come later in the loop.
    \Illuminate\Support\Facades\Http::fake(['api.fonnte.com/*' => \Illuminate\Support\Facades\Http::response(['status' => true], 200)]);
    \App\Models\WhatsAppTemplate::firstOrCreate(
        ['kode' => 'reminder_sesi_h1'],
        ['isi_template' => 'Sesi {nama_siswa} besok di {lokasi_sesi} jam {tanggal_sesi}.']
    );

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    // First kasus/sesi: peserta = 'siswa', siswa has a linked User with a real email. This is
    // the one that previously crashed the mail send mid-loop.
    $siswaUser = User::factory()->create(['lembaga_id' => $lembaga->id, 'email' => 'siswa.reminder@example.test']);
    $siswaSatu = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'user_id' => $siswaUser->id]);
    $kasusSatu = Kasus::create([
        'siswa_id' => $siswaSatu->id, 'lembaga_id' => $lembaga->id,
        'kategori_masalah' => 'Perilaku', 'deskripsi' => 'Contoh siswa.',
    ]);
    KasusSesi::factory()->create([
        'kasus_id' => $kasusSatu->id, 'peserta' => 'siswa',
        'dijadwalkan_pada' => now()->addDay()->setTime(8, 0), 'status' => 'terjadwal',
    ]);

    // Second, separate kasus/sesi: peserta = 'orang_tua'.
    $siswaDua = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Siswa Dua']);
    $orangTuaUser = User::factory()->create(['lembaga_id' => null]);
    Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $orangTuaUser->assignRole('orang_tua');
    $orangTuaDua = OrangTua::create([
        'user_id' => $orangTuaUser->id, 'nama_lengkap' => 'Ibu Dua',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200007701',
        'email' => 'ortu.dua@example.test',
    ]);
    $siswaDua->orangTua()->attach($orangTuaDua->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);
    $kasusDua = Kasus::create([
        'siswa_id' => $siswaDua->id, 'lembaga_id' => $lembaga->id,
        'kategori_masalah' => 'Perilaku', 'deskripsi' => 'Contoh orang tua.',
    ]);
    KasusSesi::factory()->create([
        'kasus_id' => $kasusDua->id, 'peserta' => 'orang_tua',
        'dijadwalkan_pada' => now()->addDay()->setTime(9, 0), 'status' => 'terjadwal',
        'lokasi_mode' => 'Ruang BK',
    ]);

    $this->artisan('kasus:kirim-reminder-sesi')->assertExitCode(0);

    // Siswa's mail reminder actually sent (proves the command did not abort on this one).
    $sentMessages = app('mailer')->getSymfonyTransport()->messages();
    $siswaMailSent = $sentMessages->contains(
        fn ($message) => in_array('siswa.reminder@example.test', array_map(
            fn ($address) => $address->getAddress(),
            $message->getOriginalMessage()->getTo()
        ), true)
    );
    expect($siswaMailSent)->toBeTrue();

    // Orang tua's whatsapp reminder also sent -- proving the loop did not abort partway
    // through and silently skip the rest.
    \Illuminate\Support\Facades\Http::assertSent(function ($request) {
        return $request->url() === 'https://api.fonnte.com/send'
            && $request['target'] === '081200007701'
            && str_contains($request['message'], 'Siswa Dua')
            && str_contains($request['message'], 'Ruang BK');
    });
});
