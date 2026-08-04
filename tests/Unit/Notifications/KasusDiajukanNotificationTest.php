<?php
// tests/Unit/Notifications/KasusDiajukanNotificationTest.php

use App\Mail\KasusDiajukanMail;
use App\Models\Guru;
use App\Models\Kasus;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\Yayasan;
use App\Notifications\KasusDiajukanNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('sends via database and mail channels, and the mail is a KasusDiajukanMail', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Budi Siswa']);
    $kasus = Kasus::factory()->create(['siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['email' => 'guru@example.test']);

    Notification::fake();

    $guru->notify(new KasusDiajukanNotification($kasus));

    Notification::assertSentTo($guru, KasusDiajukanNotification::class, function ($notification) use ($kasus, $guru) {
        $viaChannels = $notification->via($guru);
        $mail = $notification->toMail($guru);
        $database = $notification->toDatabase($guru);

        return $viaChannels === ['database', 'mail']
            && $mail instanceof KasusDiajukanMail
            && $database['kasus_id'] === $kasus->id;
    });
});
