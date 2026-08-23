<?php
// tests/Feature/Keuangan/TagihanDiterbitkanNotificationTest.php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Notifications\Finance\TagihanDiterbitkanNotification;
use App\Services\TagihanBillingGenerator;
use Illuminate\Support\Facades\Notification;

it('sends TagihanDiterbitkanNotification to the kontak utama when a tagihan is generated', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $orangTua = OrangTua::factory()->create();
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'default_amount' => 500000, 'mode' => 'otomatis']);

    app(TagihanBillingGenerator::class)->generateForSiswa($siswa, $jenisTagihan, 'manual');

    Notification::assertSentTo($orangTua, TagihanDiterbitkanNotification::class);
});

it('does not send when generateForSiswa returns false because the tagihan already exists', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $orangTua = OrangTua::factory()->create();
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'default_amount' => 500000, 'mode' => 'otomatis']);

    $generator = app(TagihanBillingGenerator::class);
    $generator->generateForSiswa($siswa, $jenisTagihan, 'manual');
    Notification::fake(); // reset the fake so only the SECOND call's sends are captured
    $generator->generateForSiswa($siswa, $jenisTagihan, 'manual');

    Notification::assertNothingSent();
});

it('is not urgent', function () {
    $notification = new TagihanDiterbitkanNotification(\App\Models\Tagihan::factory()->make());
    expect($notification->isUrgent())->toBeFalse();
});
