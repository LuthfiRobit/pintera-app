<?php

use App\Domains\Akademik\Models\Presensi;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Domains\Akademik\Services\PresensiNotificationService;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use App\Notifications\Akademik\PresensiPengecualianNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function buatPresensiUntukNotifikasi(string $status): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    $sesi = SesiPembelajaran::factory()->create(['kelas_id' => $kelas->id]);
    $presensi = Presensi::factory()->create(['sesi_pembelajaran_id' => $sesi->id, 'siswa_id' => $siswa->id, 'status' => $status]);

    return [$siswa, $presensi];
}

it('mengirim notifikasi ke kontak utama saat status berubah jadi izin', function () {
    Notification::fake();
    [$siswa, $presensi] = buatPresensiUntukNotifikasi('izin');
    $kontakUtama = OrangTua::factory()->create();
    $siswa->orangTua()->attach($kontakUtama->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    (new PresensiNotificationService)->kirimJikaPerluAtasPerubahan($presensi, 'hadir');

    Notification::assertSentTo($kontakUtama, PresensiPengecualianNotification::class);
});

it('tidak mengirim notifikasi kalau status tidak berubah', function () {
    Notification::fake();
    [$siswa, $presensi] = buatPresensiUntukNotifikasi('izin');
    $kontakUtama = OrangTua::factory()->create();
    $siswa->orangTua()->attach($kontakUtama->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    (new PresensiNotificationService)->kirimJikaPerluAtasPerubahan($presensi, 'izin');

    Notification::assertNothingSent();
});

it('tidak mengirim notifikasi kalau status baru adalah hadir', function () {
    Notification::fake();
    [$siswa, $presensi] = buatPresensiUntukNotifikasi('hadir');
    $kontakUtama = OrangTua::factory()->create();
    $siswa->orangTua()->attach($kontakUtama->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    (new PresensiNotificationService)->kirimJikaPerluAtasPerubahan($presensi, 'alpa');

    Notification::assertNothingSent();
});

it('tidak error dan tidak mengirim apa pun kalau siswa tidak punya kontak utama', function () {
    Notification::fake();
    [$siswa, $presensi] = buatPresensiUntukNotifikasi('sakit');
    // Sengaja TIDAK attach orang tua sama sekali.

    (new PresensiNotificationService)->kirimJikaPerluAtasPerubahan($presensi, 'hadir');

    Notification::assertNothingSent();
});

it('mengirim notifikasi untuk keempat status pengecualian: izin, sakit, alpa, terlambat', function (string $status) {
    Notification::fake();
    [$siswa, $presensi] = buatPresensiUntukNotifikasi($status);
    $kontakUtama = OrangTua::factory()->create();
    $siswa->orangTua()->attach($kontakUtama->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);

    (new PresensiNotificationService)->kirimJikaPerluAtasPerubahan($presensi, 'hadir');

    Notification::assertSentTo($kontakUtama, PresensiPengecualianNotification::class);
})->with(['izin', 'sakit', 'alpa', 'terlambat']);
