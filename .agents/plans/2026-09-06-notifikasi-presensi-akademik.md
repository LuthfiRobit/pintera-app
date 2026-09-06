# Notifikasi Presensi Akademik (Opsi A2) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Kirim notifikasi WhatsApp ke kontak utama orang tua saat status presensi siswa berubah jadi Izin/Sakit/Alpa/Terlambat lewat Jurnal KBM guru.

**Architecture:** Satu Service baru (`PresensiNotificationService`) yang menerima pasangan status (lama, baru) dan menentukan perlu-tidaknya kirim notifikasi — dipanggil dari `RecordJurnalDanPresensiAction` setelah transaksi DB commit. Satu Notification class baru (`PresensiPengecualianNotification`) reuse pola `KonselorDipilihNotification` (extends `Notification` langsung, bukan base class custom). Pesan lewat `WhatsAppTemplate` (database-driven).

**Tech Stack:** Laravel 12 Notifications (channel `database` + `mail` + `whatsapp` custom), Pest.

## Global Constraints

- Status yang memicu notifikasi: `izin`, `sakit`, `alpa`, **dan** `terlambat` (keempatnya — bukan cuma 3).
- Penerima: kontak utama saja (`siswa_orang_tua.is_kontak_utama = true`), bukan semua orang tua yang terhubung.
- Notifikasi dikirim ke model `OrangTua` LANGSUNG (`Notification::send($kontakUtama, ...)`) — `OrangTua` sudah `Notifiable` sendiri (`routeNotificationForMail()`/`routeNotificationForWhatsapp()` sudah ada), TIDAK PERLU resolve ke `User`.
- Notifikasi WAJIB dikirim DI LUAR transaksi DB (setelah `DB::transaction()` selesai, bukan di dalamnya) — network I/O tidak boleh menahan transaksi terbuka.
- Kegagalan kirim notifikasi (exception apa pun) TIDAK BOLEH menggagalkan penyimpanan Jurnal KBM — dibungkus try/catch + `Log::error()` di dalam `PresensiNotificationService`, bukan di Action.
- Notification class extends `Illuminate\Notifications\Notification` LANGSUNG — JANGAN bikin base class custom ala `FinanceNotification` (itu spesifik Finance, tidak relevan di sini).
- JANGAN pakai `App\Services\Finance\NotificationDispatcher` — itu wrapper khusus Finance. Pakai `Illuminate\Support\Facades\Notification` biasa.
- TIDAK ADA toggle Lembaga, TIDAK ADA gating platform/Yayasan, TIDAK ADA penyebutan "check-in"/"tap"/opsi fisik apa pun di kode/nama class/pesan.
- Tidak pakai worktree, kerja langsung di branch `akademik-v2`.

---

## File Structure

- **Create**: `app/Domains/Akademik/Services/PresensiNotificationService.php` — logic deteksi-perubahan + kirim notifikasi. Reusable untuk A3 nanti (di luar cakupan plan ini).
- **Create**: `app/Notifications/Akademik/PresensiPengecualianNotification.php` — isi pesan notifikasi.
- **Modify**: `app/Domains/Akademik/Actions/Presensi/RecordJurnalDanPresensiAction.php` — panggil Service setelah simpan.
- **Modify**: `database/seeders/WhatsAppTemplateSeeder.php` — tambah 1 entri template.
- **Modify**: `tests/Feature/Guru/JurnalKbmControllerTest.php` — tambah test end-to-end (Task 3).
- **Create**: `tests/Unit/Services/PresensiNotificationServiceTest.php`, `tests/Unit/Notifications/PresensiPengecualianNotificationTest.php`.

---

### Task 1: `PresensiNotificationService`

**Files:**
- Create: `app/Domains/Akademik/Services/PresensiNotificationService.php`
- Test: `tests/Unit/Services/PresensiNotificationServiceTest.php`

**Interfaces:**
- Consumes: `App\Domains\Akademik\Models\Presensi` (relasi `siswa()`, cast `status` ke `App\Domains\Akademik\Enums\StatusPresensi`), `App\Models\Siswa::orangTua()` (BelongsToMany, pivot `is_kontak_utama`), `App\Notifications\Akademik\PresensiPengecualianNotification` (dari Task 2 — signature `__construct(public Presensi $presensi)`).
- Produces: `PresensiNotificationService::kirimJikaPerluAtasPerubahan(Presensi $presensi, ?string $statusLama): void` — dipakai Task 3.

- [ ] **Step 1: Tulis test yang gagal**

```php
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
use Illuminate\Support\Facades\Notification;

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

    Notification::assertSentTo($kontakUtama, \App\Notifications\Akademik\PresensiPengecualianNotification::class);
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

    Notification::assertSentTo($kontakUtama, \App\Notifications\Akademik\PresensiPengecualianNotification::class);
})->with(['izin', 'sakit', 'alpa', 'terlambat']);
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Unit/Services/PresensiNotificationServiceTest.php --compact`
Expected: FAIL — `Class "App\Domains\Akademik\Services\PresensiNotificationService" not found` (dan `PresensiPengecualianNotification` juga belum ada, akan dibuat Task 2 — untuk Task 1 ini, buat dulu **kelas Notification kosong minimal** supaya test Task 1 bisa jalan, isi lengkapnya di Task 2).

Untuk sementara di Task 1, buat `app/Notifications/Akademik/PresensiPengecualianNotification.php` versi minimal (akan dilengkapi Task 2):

```php
<?php

namespace App\Notifications\Akademik;

use App\Domains\Akademik\Models\Presensi;
use Illuminate\Notifications\Notification;

class PresensiPengecualianNotification extends Notification
{
    public function __construct(public Presensi $presensi)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return ['presensi_id' => $this->presensi->id];
    }
}
```

- [ ] **Step 3: Tulis implementasi**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Services;

use App\Domains\Akademik\Enums\StatusPresensi;
use App\Domains\Akademik\Models\Presensi;
use App\Notifications\Akademik\PresensiPengecualianNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

final class PresensiNotificationService
{
    private const STATUS_PENGECUALIAN = ['izin', 'sakit', 'alpa', 'terlambat'];

    public function kirimJikaPerluAtasPerubahan(Presensi $presensi, ?string $statusLama): void
    {
        $statusBaru = $presensi->status instanceof StatusPresensi ? $presensi->status->value : (string) $presensi->status;

        if ($statusBaru === $statusLama) {
            return;
        }

        if (! in_array($statusBaru, self::STATUS_PENGECUALIAN, true)) {
            return;
        }

        $kontakUtama = $presensi->siswa->orangTua()->wherePivot('is_kontak_utama', true)->first();

        if ($kontakUtama === null) {
            return;
        }

        try {
            Notification::send($kontakUtama, new PresensiPengecualianNotification($presensi));
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim PresensiPengecualianNotification: '.$e->getMessage());
        }
    }
}
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test tests/Unit/Services/PresensiNotificationServiceTest.php --compact`
Expected: **8 passed** (4 test tunggal + 4 dari `->with([...])` di test terakhir).

- [ ] **Step 5: Commit**

```bash
git add app/Domains/Akademik/Services/PresensiNotificationService.php app/Notifications/Akademik/PresensiPengecualianNotification.php tests/Unit/Services/PresensiNotificationServiceTest.php
git commit -m "feat(akademik): PresensiNotificationService -- deteksi perubahan status & kirim notifikasi ke kontak utama

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 2: Lengkapi `PresensiPengecualianNotification`

**Files:**
- Modify: `app/Notifications/Akademik/PresensiPengecualianNotification.php` (lengkapi versi minimal dari Task 1)
- Test: `tests/Unit/Notifications/PresensiPengecualianNotificationTest.php`

**Interfaces:**
- Consumes: `App\Models\WhatsAppTemplate::renderKode(string $kode, array $placeholders): ?string`, `Presensi->siswa->nama_lengkap`, `Presensi->status->label()` (`StatusPresensi::label()`), `Presensi->sesiPembelajaran->tanggal` (Carbon, sudah cast), `Presensi->keterangan` (nullable string).
- Produces: `toDatabase()`, `toWhatsApp()`, `via()` lengkap — dipakai Task 3 (test end-to-end) dan Task 4 (seeder template yang dirujuk `toWhatsApp()`).

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

use App\Domains\Akademik\Models\Presensi;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\WhatsAppTemplate;
use App\Models\Yayasan;
use App\Notifications\Akademik\PresensiPengecualianNotification;

it('toDatabase berisi nama siswa, status label, dan tanggal', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'nama_lengkap' => 'Ahmad Fauzi']);
    $sesi = SesiPembelajaran::factory()->create(['kelas_id' => $kelas->id, 'tanggal' => '2026-09-10']);
    $presensi = Presensi::factory()->create(['sesi_pembelajaran_id' => $sesi->id, 'siswa_id' => $siswa->id, 'status' => 'sakit', 'keterangan' => 'Demam tinggi']);

    $data = (new PresensiPengecualianNotification($presensi))->toDatabase(null);

    expect($data['presensi_id'])->toBe($presensi->id);
    expect($data['message'])->toContain('Ahmad Fauzi');
    expect($data['message'])->toContain('Sakit');
});

it('toWhatsApp merender template dengan nama, status, tanggal, dan keterangan', function () {
    WhatsAppTemplate::factory()->create([
        'kode' => 'presensi_pengecualian',
        'isi_template' => 'Yth. Orang Tua {nama_siswa}, presensi tercatat {status} pada {tanggal}. Keterangan: {keterangan}.',
    ]);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'nama_lengkap' => 'Bunga Lestari']);
    $sesi = SesiPembelajaran::factory()->create(['kelas_id' => $kelas->id, 'tanggal' => '2026-09-10']);
    $presensi = Presensi::factory()->create(['sesi_pembelajaran_id' => $sesi->id, 'siswa_id' => $siswa->id, 'status' => 'izin', 'keterangan' => 'Acara keluarga']);

    $pesan = (new PresensiPengecualianNotification($presensi))->toWhatsApp(null);

    expect($pesan)->toContain('Bunga Lestari');
    expect($pesan)->toContain('Izin');
    expect($pesan)->toContain('Acara keluarga');
});

it('toWhatsApp menampilkan tanda strip kalau keterangan kosong', function () {
    WhatsAppTemplate::factory()->create([
        'kode' => 'presensi_pengecualian',
        'isi_template' => 'Keterangan: {keterangan}.',
    ]);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    $sesi = SesiPembelajaran::factory()->create(['kelas_id' => $kelas->id]);
    $presensi = Presensi::factory()->create(['sesi_pembelajaran_id' => $sesi->id, 'siswa_id' => $siswa->id, 'status' => 'alpa', 'keterangan' => null]);

    $pesan = (new PresensiPengecualianNotification($presensi))->toWhatsApp(null);

    expect($pesan)->toContain('Keterangan: -.');
});
```

Catatan: kalau `WhatsAppTemplate` belum punya factory (`database/factories/WhatsAppTemplateFactory.php`), buat dulu versi minimal:
```php
<?php

namespace Database\Factories;

use App\Models\WhatsAppTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class WhatsAppTemplateFactory extends Factory
{
    protected $model = WhatsAppTemplate::class;

    public function definition(): array
    {
        return [
            'kode' => $this->faker->unique()->word(),
            'isi_template' => 'Template {placeholder}.',
            'deskripsi' => $this->faker->sentence(),
        ];
    }
}
```
(cek dulu apakah file ini sudah ada sebelum bikin baru — kalau sudah ada, pakai yang existing, jangan timpa.)

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Unit/Notifications/PresensiPengecualianNotificationTest.php --compact`
Expected: FAIL — `toDatabase()`/`toWhatsApp()` belum sesuai (versi minimal Task 1 belum punya `message` di `toDatabase()`, dan belum ada `toWhatsApp()` sama sekali).

- [ ] **Step 3: Lengkapi implementasi**

```php
<?php

namespace App\Notifications\Akademik;

use App\Domains\Akademik\Models\Presensi;
use App\Models\WhatsAppTemplate;
use Illuminate\Notifications\Notification;

class PresensiPengecualianNotification extends Notification
{
    public function __construct(public Presensi $presensi)
    {
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (filled($notifiable->routeNotificationFor('mail'))) {
            $channels[] = 'mail';
        }

        $channels[] = 'whatsapp';

        return $channels;
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'presensi_id' => $this->presensi->id,
            'message' => "Presensi {$this->presensi->siswa->nama_lengkap} tercatat {$this->presensi->status->label()} pada {$this->presensi->sesiPembelajaran->tanggal->translatedFormat('d F Y')}.",
        ];
    }

    public function toWhatsApp(object $notifiable): ?string
    {
        return WhatsAppTemplate::renderKode('presensi_pengecualian', [
            'nama_siswa' => $this->presensi->siswa->nama_lengkap,
            'status' => $this->presensi->status->label(),
            'tanggal' => $this->presensi->sesiPembelajaran->tanggal->translatedFormat('d F Y'),
            'keterangan' => $this->presensi->keterangan ?: '-',
        ]);
    }
}
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test tests/Unit/Notifications/PresensiPengecualianNotificationTest.php --compact`
Expected: **3 passed**.

- [ ] **Step 5: Jalankan ulang test Task 1, pastikan masih lulus** (Task 1 juga pakai class ini)

Run: `php artisan test tests/Unit/Services/PresensiNotificationServiceTest.php --compact`
Expected: **8 passed** (tidak boleh ada regresi dari perubahan `via()`).

- [ ] **Step 6: Commit**

```bash
git add app/Notifications/Akademik/PresensiPengecualianNotification.php tests/Unit/Notifications/PresensiPengecualianNotificationTest.php database/factories/WhatsAppTemplateFactory.php
git commit -m "feat(akademik): lengkapi PresensiPengecualianNotification -- toDatabase, toWhatsApp via WhatsAppTemplate

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```
(Kalau `WhatsAppTemplateFactory.php` sudah ada sebelumnya dan tidak diubah, jangan ikut di-`git add`.)

---

### Task 3: Sambungkan ke `RecordJurnalDanPresensiAction`

**Files:**
- Modify: `app/Domains/Akademik/Actions/Presensi/RecordJurnalDanPresensiAction.php`
- Modify: `tests/Feature/Guru/JurnalKbmControllerTest.php` (file sudah ada, JANGAN buat file test baru terpisah)

**Interfaces:**
- Consumes: `PresensiNotificationService::kirimJikaPerluAtasPerubahan(Presensi, ?string): void` (Task 1).
- Produces: `RecordJurnalDanPresensiAction::execute()` sekarang otomatis memicu notifikasi presensi — dipakai apa adanya oleh `Guru\JurnalKbmController::update()` (tidak perlu diubah, dependency baru di-resolve otomatis oleh container Laravel).

- [ ] **Step 1: Baca file existing untuk konfirmasi tidak ada drift**

Run: `cat app/Domains/Akademik/Actions/Presensi/RecordJurnalDanPresensiAction.php`
Expected isi PERSIS:
```php
<?php

namespace App\Domains\Akademik\Actions\Presensi;

use App\Domains\Akademik\DataTransferObjects\JurnalPresensiData;
use App\Domains\Akademik\Models\SesiPembelajaran;
use Illuminate\Support\Facades\DB;

final class RecordJurnalDanPresensiAction
{
    public function execute(SesiPembelajaran $sesi, JurnalPresensiData $data): SesiPembelajaran
    {
        return DB::transaction(function () use ($sesi, $data) {
            $sesi->update(['materi' => $data->materi]);

            foreach ($data->presensi as $siswaId => $status) {
                $sesi->presensi()->where('siswa_id', $siswaId)->update([
                    'status' => $status,
                    'keterangan' => $data->keterangan[$siswaId] ?? null,
                ]);
            }

            return $sesi->fresh();
        });
    }
}
```
Kalau isinya BEDA dari ini, STOP dan laporkan — jangan lanjutkan dengan asumsi kode di plan ini yang benar.

- [ ] **Step 2: Tulis test yang gagal (tambah ke file existing)**

Tambahkan ke `tests/Feature/Guru/JurnalKbmControllerTest.php`, setelah test `'saves keterangan per siswa alongside status izin/sakit'` yang sudah ada:

```php
it('mengirim notifikasi presensi ke kontak utama saat status berubah jadi izin', function () {
    Notification::fake();
    ['guruUser' => $guruUser, 'siswa' => $siswa] = siapkanGuruDenganJadwalHariIni();
    $this->actingAs($guruUser)->get(route('guru.jurnal-kbm.index'));
    $sesi = SesiPembelajaran::firstOrFail();
    $kontakUtama = \App\Models\OrangTua::factory()->create();
    $siswa->orangTua()->attach($kontakUtama->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    $this->actingAs($guruUser)->put(route('guru.jurnal-kbm.update', $sesi), [
        'materi' => 'Perkalian dan pembagian',
        'presensi' => [$siswa->id => 'izin'],
    ]);

    Notification::assertSentTo($kontakUtama, \App\Notifications\Akademik\PresensiPengecualianNotification::class);
});

it('tidak mengirim notifikasi presensi kalau siswa disimpan tetap hadir', function () {
    Notification::fake();
    ['guruUser' => $guruUser, 'siswa' => $siswa] = siapkanGuruDenganJadwalHariIni();
    $this->actingAs($guruUser)->get(route('guru.jurnal-kbm.index'));
    $sesi = SesiPembelajaran::firstOrFail();
    $kontakUtama = \App\Models\OrangTua::factory()->create();
    $siswa->orangTua()->attach($kontakUtama->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    $this->actingAs($guruUser)->put(route('guru.jurnal-kbm.update', $sesi), [
        'materi' => 'Perkalian dan pembagian',
        'presensi' => [$siswa->id => 'hadir'],
    ]);

    Notification::assertNothingSent();
});
```

Tambahkan `use Illuminate\Support\Facades\Notification;` ke bagian `use` di atas file kalau belum ada.

- [ ] **Step 3: Jalankan test baru, pastikan gagal**

Run: `php artisan test tests/Feature/Guru/JurnalKbmControllerTest.php --compact`
Expected: 2 test baru FAIL (notifikasi belum pernah dikirim sama sekali dari Action ini), test-test lama tetap PASS (belum ada perubahan ke Action).

- [ ] **Step 4: Modifikasi Action**

```php
<?php

namespace App\Domains\Akademik\Actions\Presensi;

use App\Domains\Akademik\DataTransferObjects\JurnalPresensiData;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Domains\Akademik\Services\PresensiNotificationService;
use Illuminate\Support\Facades\DB;

final class RecordJurnalDanPresensiAction
{
    public function __construct(
        private readonly PresensiNotificationService $presensiNotificationService,
    ) {
    }

    public function execute(SesiPembelajaran $sesi, JurnalPresensiData $data): SesiPembelajaran
    {
        $perluDicek = [];

        $sesiTerbaru = DB::transaction(function () use ($sesi, $data, &$perluDicek) {
            $sesi->update(['materi' => $data->materi]);

            $statusLamaPerSiswa = $sesi->presensi()->get()->keyBy('siswa_id')
                ->map(fn ($p) => $p->status->value);

            foreach ($data->presensi as $siswaId => $status) {
                $sesi->presensi()->where('siswa_id', $siswaId)->update([
                    'status' => $status,
                    'keterangan' => $data->keterangan[$siswaId] ?? null,
                ]);

                $perluDicek[] = ['siswa_id' => $siswaId, 'status_lama' => $statusLamaPerSiswa->get($siswaId)];
            }

            return $sesi->fresh();
        });

        // WAJIB di luar transaksi -- pengiriman notifikasi (network I/O ke WhatsApp
        // Gateway) tidak boleh menahan transaksi DB terbuka.
        foreach ($perluDicek as $item) {
            $presensiTerbaru = $sesiTerbaru->presensi()->where('siswa_id', $item['siswa_id'])->first();
            if ($presensiTerbaru !== null) {
                $this->presensiNotificationService->kirimJikaPerluAtasPerubahan($presensiTerbaru, $item['status_lama']);
            }
        }

        return $sesiTerbaru;
    }
}
```

- [ ] **Step 5: Jalankan full file test, pastikan semua lulus (baru + lama, tidak ada regresi)**

Run: `php artisan test tests/Feature/Guru/JurnalKbmControllerTest.php --compact`
Expected: **11 passed** (9 test lama + 2 test baru).

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Akademik/Actions/Presensi/RecordJurnalDanPresensiAction.php tests/Feature/Guru/JurnalKbmControllerTest.php
git commit -m "feat(akademik): RecordJurnalDanPresensiAction kirim notifikasi presensi setelah simpan jurnal

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 4: Seeder Template WhatsApp

**Files:**
- Modify: `database/seeders/WhatsAppTemplateSeeder.php`

**Interfaces:**
- Consumes: `App\Models\WhatsAppTemplate::firstOrCreate()`.
- Produces: row `kode='presensi_pengecualian'` di tabel `whatsapp_templates` — dirujuk `PresensiPengecualianNotification::toWhatsApp()` (Task 2) di lingkungan nyata (dev/prod), BUKAN test (test Task 2 sudah buat row sendiri via factory).

- [ ] **Step 1: Baca isi file existing untuk tahu tempat menambahkan entri baru**

Run: `cat database/seeders/WhatsAppTemplateSeeder.php`

- [ ] **Step 2: Tambahkan entri baru**

Tambahkan di akhir method `run()`, sebelum kurung kurawal penutup:

```php
        WhatsAppTemplate::firstOrCreate(['kode' => 'presensi_pengecualian'], [
            'isi_template' => 'Yth. Orang Tua {nama_siswa}, presensi tercatat {status} pada {tanggal}. Keterangan: {keterangan}.',
            'deskripsi' => 'Dikirim ke kontak utama orang tua saat presensi siswa dicatat guru sebagai Izin/Sakit/Alpa/Terlambat. Placeholder tersedia: {nama_siswa}, {status}, {tanggal}, {keterangan}.',
        ]);
```

- [ ] **Step 3: Jalankan seeder, verifikasi tidak error**

Run: `php artisan db:seed --class=WhatsAppTemplateSeeder`
Expected: selesai tanpa error.

- [ ] **Step 4: Verifikasi row masuk**

Run:
```
php artisan tinker --execute '
$t = App\Models\WhatsAppTemplate::where("kode", "presensi_pengecualian")->first();
echo $t ? "OK: {$t->isi_template}" : "TIDAK DITEMUKAN";
'
```
Expected: `OK: Yth. Orang Tua {nama_siswa}, presensi tercatat {status} pada {tanggal}. Keterangan: {keterangan}.`

- [ ] **Step 5: Commit**

```bash
git add database/seeders/WhatsAppTemplateSeeder.php
git commit -m "feat(akademik): tambah template WA presensi_pengecualian

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 5: Full Test Suite & Penutup

**Files:**
- Create: `.agents/logs/2026-09-06-notifikasi-presensi-akademik.md`
- Modify: `PETA_PENGEMBANGAN.md`

- [ ] **Step 1: Pastikan tidak ada proses test lain berjalan**

Run (PowerShell): `Get-CimInstance Win32_Process -Filter "Name='php.exe'" | Select ProcessId,CommandLine`
Kalau ada proses test-runner sisa (bukan `php artisan serve`), matikan dulu sebelum lanjut.

- [ ] **Step 2: Full test suite**

Run: `php artisan test --compact`
Expected: 0 failures (bandingkan angka `passed` dengan baseline sebelum plan ini dimulai — cek commit terakhir sebelum Task 1 kalau perlu).

- [ ] **Step 3: Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `{"tool":"pint","result":"passed"}` atau `"fixed"` (kalau ada yang di-auto-fix, commit lagi terpisah).

- [ ] **Step 4: Tulis handoff log**

Tulis `.agents/logs/2026-09-06-notifikasi-presensi-akademik.md` — ringkas apa yang dibangun, jumlah test baru, commit hash tiap task.

- [ ] **Step 5: Update `PETA_PENGEMBANGAN.md`**

Tambahkan entri baru merangkum fitur ini, dan **update baris roadmap lama** "Notifikasi presensi & penjemputan (tap-in/tap-out)" di §4 Level Orang Tua/Wali — pecah jadi 2 baris terpisah sesuai keputusan sesi ini: "Notifikasi Presensi Akademik (Jurnal)" = ✅ Ada, dan "Presensi Fisik/Check-in-Check-out (Kartu Pelajar QR)" = tetap Belum Ada (proyek terpisah).

- [ ] **Step 6: Commit**

```bash
git add .agents/logs/2026-09-06-notifikasi-presensi-akademik.md PETA_PENGEMBANGAN.md
git commit -m "docs(akademik): handoff log & update roadmap -- notifikasi presensi akademik selesai

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Self-Review

**1. Cakupan spec**: §2.1 (4 status) → Task 1 test parametrik. §2.2 (kontak utama) → Task 1. §2.3 (deteksi perubahan) → Task 1 & 3. §2.4 (no gating) → tidak ada task yang membangunnya, sesuai. §2.5 (gagal tidak menggagalkan) → Task 1 try/catch. §2.6 (pola notification sederhana) → Task 2. §2.7 (WhatsAppTemplate) → Task 2 & 4. §2.8 (penamaan netral) → semua nama class/variable sudah dicek tidak menyinggung check-in/tap. §4 (desain utk A3) → Service menerima parameter murni (Presensi, ?status lama), tidak berasumsi sumber input.

**2. Placeholder scan**: semua step berisi kode lengkap, tidak ada "TBD"/"handle it"/dsb.

**3. Konsistensi tipe**: `PresensiNotificationService::kirimJikaPerluAtasPerubahan(Presensi $presensi, ?string $statusLama): void` dipakai identik di Task 1 (definisi) dan Task 3 (pemanggilan). `PresensiPengecualianNotification::__construct(public Presensi $presensi)` konsisten Task 1 (versi minimal) → Task 2 (versi lengkap, signature tidak berubah) → Task 3 (dipakai di assertion test).
