# Kartu Digital Siswa & Presensi via Scan (Opsi A3) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Siswa memindai kode QR pribadi mereka sendiri untuk presensi di Jurnal KBM guru (Opsi A3), sekaligus membangun fondasi "Kartu Digital Siswa" generik yang bisa dipakai fitur lain di masa depan.

**Architecture:** 1 tabel generik `kartu_siswa` (kolom `tipe`, sekarang cuma `'qr'`) dengan 1 titik resolusi tunggal `KartuSiswa::resolveSiswa()`. Siswa lihat & generate kode QR mereka sendiri di Portal Siswa (render server-side via `simplesoftwareio/simple-qrcode`, tanpa dependency baru). Guru scan pakai kamera browser (reuse `qr-camera-scanner.js` yang sudah ada dari modul SDM) di halaman Jurnal KBM yang sudah ada — hasil scan cuma mengisi otomatis form presensi manual yang sudah ada, TIDAK ada jalur simpan baru. Admin kelola per-siswa lewat 1 tab baru di halaman Data Siswa yang sudah ada.

**Tech Stack:** Laravel 12, PHP 8.3, Pest, Blade + Alpine.js, `simplesoftwareio/simple-qrcode` (Composer, sudah terinstall), `html5-qrcode` via `resources/js/qr-camera-scanner.js` (npm, sudah terinstall & reusable apa adanya).

## Global Constraints

- Model data presensi A2 (`RecordJurnalDanPresensiAction`, `PresensiNotificationService`, `PresensiPengecualianNotification`) TIDAK disentuh sama sekali oleh plan ini.
- Kode QR permanen per siswa (bukan rotating/OTP) — "generate ulang" mengganti nilai `kode` pada baris yang sama, TIDAK membuat baris baru (supaya tidak melanggar unique constraint `(siswa_id, tipe)`).
- Kolom `tipe` di `kartu_siswa` adalah native DB ENUM (`$table->enum('tipe', ['qr'])`) sesuai `.ai/rules/migrations.md` — untuk sekarang cuma nilai `'qr'`. RFID HANYA disiapkan strukturnya (kolom `tipe` bisa nanti di-`ALTER` tambah nilai `'rfid'`), TIDAK ADA Action/reader/alur registrasi RFID fungsional di plan ini.
- TIDAK ADA toggle Lembaga untuk memilih mode A2 vs A3.
- Validasi scan 3 lapis WAJIB PERSIS urutan: kartu ditemukan & aktif → siswa satu kelas dengan sesi → siswa satu lembaga dengan guru. Kegagalan di lapis mana pun langsung berhenti (tidak lanjut cek lapis berikutnya) dan tidak menandai kehadiran apa pun.
- Render QR pakai `SimpleSoftwareIO\QrCode\Facades\QrCode::size(...)->generate($kode)` (SVG server-side) — BUKAN API eksternal (`api.qrserver.com`). TIDAK ADA dependency npm baru — `html5-qrcode` dipakai apa adanya lewat `resources/js/qr-camera-scanner.js` yang sudah ada.
- Admin kelola kartu siswa lewat 1 TAB baru di halaman Data Siswa yang sudah ada (`admin/siswa/{siswa}/edit`) — BUKAN halaman/menu sidebar baru.
- Scan bisa override entri manual jadi Hadir; field presensi manual tetap editable setelahnya untuk koreksi guru.
- Hindari istilah "check-in"/"tap"/gerbang di kode, nama class, pesan, dan komentar.
- Semua file baru di domain Akademik masuk `app/Domains/Akademik/...` (`.ai/rules/domains.md` — TIDAK ADA file baru di `app/Models/`/`app/Enums/` legacy zone).
- Model pakai `$fillable` (bukan `$guarded`), TIDAK ADA Observer/lifecycle-hook closure, primary key auto-increment default (`.ai/rules/domains-models.md`).
- Action = 1 method `execute()` + constructor DI (`.ai/rules/actions.md`).
- Tidak ada Repository/Query-object layer; JSON response dibangun manual via `response()->json([...])`, tidak ada API Resource class; URL generation selalu `route()` (`.ai/rules/controllers.md`).
- Tidak pakai worktree, kerja langsung di branch `akademik-v2`.

---

## File Structure

- **Create**: `database/migrations/2026_09_06_000001_create_kartu_siswa_table.php`
- **Create**: `app/Domains/Akademik/Models/KartuSiswa.php`
- **Create**: `app/Domains/Akademik/Exceptions/KartuValidasiException.php`, `KartuTidakValidException.php`, `KartuKelasMismatchException.php`, `KartuLembagaMismatchException.php`
- **Create**: `app/Domains/Akademik/Actions/KartuSiswa/GetOrCreateKartuQrSiswaAction.php`, `GenerateUlangKartuQrSiswaAction.php`, `NonaktifkanKartuQrSiswaAction.php`, `ResolveKartuUntukPresensiAction.php`
- **Create**: `app/Http/Controllers/Admin/KartuSayaController.php`
- **Modify**: `app/Http/Controllers/Admin/SiswaController.php` — tambah `generateUlangKartu()`, `nonaktifkanKartu()`
- **Modify**: `app/Http/Controllers/Guru/Akademik/JurnalKbmController.php` — tambah `resolveKartu()`
- **Create**: `resources/views/admin/siswa-akademik/kartu-saya.blade.php`
- **Create**: `resources/views/admin/siswa/tabs/kartu-digital.blade.php`
- **Modify**: `resources/views/admin/siswa/edit.blade.php` — tambah 1 tombol tab + 1 `@include`
- **Modify**: `resources/views/portals/guru/akademik/jurnal-kbm/show.blade.php` — tambah tombol + modal scan
- **Modify**: `resources/views/layouts/sidebar.blade.php` — tambah 1 item menu siswa
- **Modify**: `routes/admin/siswa-akademik.php`, `routes/admin/siswa.php`, `routes/guru.php`
- **Modify**: `PETA_PENGEMBANGAN.md`
- **Create**: `.agents/logs/2026-09-06-kartu-digital-siswa-presensi-scan.md`

---

### Task 1: Migrasi `kartu_siswa` + Model `KartuSiswa`

**Files:**
- Create: `database/migrations/2026_09_06_000001_create_kartu_siswa_table.php`
- Create: `app/Domains/Akademik/Models/KartuSiswa.php`
- Test: `tests/Unit/Domains/Akademik/KartuSiswaTest.php`

**Interfaces:**
- Produces: `KartuSiswa::resolveSiswa(string $kode): ?Siswa` — dipakai Task 4 (get-or-create) dan Task 3 (resolve presensi). `KartuSiswa` model dengan `$fillable = ['siswa_id', 'tipe', 'kode', 'is_active']`, relasi `siswa(): BelongsTo`, scope `aktif()`.

- [ ] **Step 1: Buat migrasi**

```bash
php artisan make:migration create_kartu_siswa_table --no-interaction
```

Isi file yang dihasilkan (ganti nama file jadi `2026_09_06_000001_create_kartu_siswa_table.php` kalau timestamp auto-generate beda):

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kartu_siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->enum('tipe', ['qr'])->default('qr');
            $table->string('kode')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['siswa_id', 'tipe']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kartu_siswa');
    }
};
```

- [ ] **Step 2: Jalankan migrasi**

Run: `php artisan migrate`
Expected: `Migrating: ..._create_kartu_siswa_table` lalu `Migrated:` tanpa error.

- [ ] **Step 3: Tulis test yang gagal**

```php
<?php

use App\Domains\Akademik\Models\KartuSiswa;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function buatSiswaUntukKartu(): Siswa
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

    return Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
}

it('resolveSiswa mengembalikan siswa untuk kode valid dan aktif', function () {
    $siswa = buatSiswaUntukKartu();
    KartuSiswa::create(['siswa_id' => $siswa->id, 'tipe' => 'qr', 'kode' => 'kode-valid-123', 'is_active' => true]);

    $hasil = KartuSiswa::resolveSiswa('kode-valid-123');

    expect($hasil)->not->toBeNull();
    expect($hasil->id)->toBe($siswa->id);
});

it('resolveSiswa mengembalikan null untuk kode yang tidak ditemukan', function () {
    $hasil = KartuSiswa::resolveSiswa('kode-tidak-ada');

    expect($hasil)->toBeNull();
});

it('resolveSiswa mengembalikan null untuk kode yang ditemukan tapi nonaktif', function () {
    $siswa = buatSiswaUntukKartu();
    KartuSiswa::create(['siswa_id' => $siswa->id, 'tipe' => 'qr', 'kode' => 'kode-nonaktif', 'is_active' => false]);

    $hasil = KartuSiswa::resolveSiswa('kode-nonaktif');

    expect($hasil)->toBeNull();
});
```

- [ ] **Step 4: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Unit/Domains/Akademik/KartuSiswaTest.php --compact`
Expected: FAIL — `Class "App\Domains\Akademik\Models\KartuSiswa" not found`.

- [ ] **Step 5: Tulis model**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Models;

use App\Models\Siswa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KartuSiswa extends Model
{
    protected $table = 'kartu_siswa';

    protected $fillable = ['siswa_id', 'tipe', 'kode', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function resolveSiswa(string $kode): ?Siswa
    {
        $kartu = self::aktif()->where('kode', $kode)->first();

        return $kartu?->siswa;
    }
}
```

- [ ] **Step 6: Jalankan test, pastikan lulus**

Run: `php artisan test tests/Unit/Domains/Akademik/KartuSiswaTest.php --compact`
Expected: **3 passed**.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_09_06_000001_create_kartu_siswa_table.php app/Domains/Akademik/Models/KartuSiswa.php tests/Unit/Domains/Akademik/KartuSiswaTest.php
git commit -m "feat(akademik): tabel & model KartuSiswa -- resolveSiswa() titik resolusi kode ke siswa

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 2: Exceptions Validasi Kartu

**Files:**
- Create: `app/Domains/Akademik/Exceptions/KartuValidasiException.php`
- Create: `app/Domains/Akademik/Exceptions/KartuTidakValidException.php`
- Create: `app/Domains/Akademik/Exceptions/KartuKelasMismatchException.php`
- Create: `app/Domains/Akademik/Exceptions/KartuLembagaMismatchException.php`

**Interfaces:**
- Produces: `KartuValidasiException` (abstract base, `extends \Exception`) dan 3 subclass konkret, masing-masing dengan pesan default Indonesia — dipakai Task 3 (`ResolveKartuUntukPresensiAction` melempar) dan Task 5 (controller menangkap `KartuValidasiException` untuk semua 3 jenis sekaligus).

- [ ] **Step 1: Tulis exception classes (tidak perlu test terpisah — class ini cuma pembawa pesan, diuji tidak langsung lewat Task 3)**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Exceptions;

abstract class KartuValidasiException extends \Exception
{
}
```

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Exceptions;

class KartuTidakValidException extends KartuValidasiException
{
    public function __construct()
    {
        parent::__construct('Kode kartu tidak valid atau sudah tidak aktif.');
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Exceptions;

class KartuKelasMismatchException extends KartuValidasiException
{
    public function __construct()
    {
        parent::__construct('Siswa ini tidak terdaftar di kelas untuk sesi ini.');
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Exceptions;

class KartuLembagaMismatchException extends KartuValidasiException
{
    public function __construct()
    {
        parent::__construct('Siswa ini tidak terdaftar di lembaga Anda.');
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Domains/Akademik/Exceptions/KartuValidasiException.php app/Domains/Akademik/Exceptions/KartuTidakValidException.php app/Domains/Akademik/Exceptions/KartuKelasMismatchException.php app/Domains/Akademik/Exceptions/KartuLembagaMismatchException.php
git commit -m "feat(akademik): exception khusus validasi kartu siswa (tidak valid, beda kelas, beda lembaga)

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 3: Action `ResolveKartuUntukPresensiAction`

**Files:**
- Create: `app/Domains/Akademik/Actions/KartuSiswa/ResolveKartuUntukPresensiAction.php`
- Test: `tests/Unit/Domains/Akademik/ResolveKartuUntukPresensiActionTest.php`

**Interfaces:**
- Consumes: `KartuSiswa::resolveSiswa(string $kode): ?Siswa` (Task 1), 3 exception (Task 2), `App\Models\Siswa` (`kelas_id`, `lembaga_id`), `App\Domains\Akademik\Models\SesiPembelajaran` (`kelas_id`).
- Produces: `ResolveKartuUntukPresensiAction::execute(string $kode, SesiPembelajaran $sesi, int $lembagaId): Siswa` — dipakai Task 5.

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

use App\Domains\Akademik\Actions\KartuSiswa\ResolveKartuUntukPresensiAction;
use App\Domains\Akademik\Exceptions\KartuKelasMismatchException;
use App\Domains\Akademik\Exceptions\KartuLembagaMismatchException;
use App\Domains\Akademik\Exceptions\KartuTidakValidException;
use App\Domains\Akademik\Models\KartuSiswa;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function buatSesiDenganKelas(Kelas $kelas): SesiPembelajaran
{
    return SesiPembelajaran::factory()->create(['kelas_id' => $kelas->id]);
}

it('mengembalikan siswa kalau kartu valid, satu kelas, dan satu lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    KartuSiswa::create(['siswa_id' => $siswa->id, 'tipe' => 'qr', 'kode' => 'kode-ok', 'is_active' => true]);
    $sesi = buatSesiDenganKelas($kelas);

    $hasil = (new ResolveKartuUntukPresensiAction)->execute('kode-ok', $sesi, $lembaga->id);

    expect($hasil->id)->toBe($siswa->id);
});

it('melempar KartuTidakValidException kalau kode tidak ditemukan', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $sesi = buatSesiDenganKelas($kelas);

    expect(fn () => (new ResolveKartuUntukPresensiAction)->execute('kode-tidak-ada', $sesi, $lembaga->id))
        ->toThrow(KartuTidakValidException::class);
});

it('melempar KartuKelasMismatchException kalau siswa beda kelas dari sesi', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasSiswa = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $kelasSesi = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelasSiswa->id]);
    KartuSiswa::create(['siswa_id' => $siswa->id, 'tipe' => 'qr', 'kode' => 'kode-beda-kelas', 'is_active' => true]);
    $sesi = buatSesiDenganKelas($kelasSesi);

    expect(fn () => (new ResolveKartuUntukPresensiAction)->execute('kode-beda-kelas', $sesi, $lembaga->id))
        ->toThrow(KartuKelasMismatchException::class);
});

it('melempar KartuLembagaMismatchException kalau siswa beda lembaga dari guru', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaSiswa = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembagaSiswa->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembagaSiswa->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembagaSiswa->id, 'kelas_id' => $kelas->id]);
    KartuSiswa::create(['siswa_id' => $siswa->id, 'tipe' => 'qr', 'kode' => 'kode-beda-lembaga', 'is_active' => true]);
    $sesi = buatSesiDenganKelas($kelas);

    expect(fn () => (new ResolveKartuUntukPresensiAction)->execute('kode-beda-lembaga', $sesi, $lembagaLain->id))
        ->toThrow(KartuLembagaMismatchException::class);
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Unit/Domains/Akademik/ResolveKartuUntukPresensiActionTest.php --compact`
Expected: FAIL — `Class "App\Domains\Akademik\Actions\KartuSiswa\ResolveKartuUntukPresensiAction" not found`.

- [ ] **Step 3: Tulis implementasi**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\KartuSiswa;

use App\Domains\Akademik\Exceptions\KartuKelasMismatchException;
use App\Domains\Akademik\Exceptions\KartuLembagaMismatchException;
use App\Domains\Akademik\Exceptions\KartuTidakValidException;
use App\Domains\Akademik\Models\KartuSiswa;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Models\Siswa;

final class ResolveKartuUntukPresensiAction
{
    public function execute(string $kode, SesiPembelajaran $sesi, int $lembagaId): Siswa
    {
        $siswa = KartuSiswa::resolveSiswa($kode);

        if ($siswa === null) {
            throw new KartuTidakValidException();
        }

        if ((int) $siswa->kelas_id !== (int) $sesi->kelas_id) {
            throw new KartuKelasMismatchException();
        }

        if ((int) $siswa->lembaga_id !== $lembagaId) {
            throw new KartuLembagaMismatchException();
        }

        return $siswa;
    }
}
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test tests/Unit/Domains/Akademik/ResolveKartuUntukPresensiActionTest.php --compact`
Expected: **4 passed**.

- [ ] **Step 5: Commit**

```bash
git add app/Domains/Akademik/Actions/KartuSiswa/ResolveKartuUntukPresensiAction.php tests/Unit/Domains/Akademik/ResolveKartuUntukPresensiActionTest.php
git commit -m "feat(akademik): ResolveKartuUntukPresensiAction -- validasi 3 lapis (kartu, kelas, lembaga)

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 4: Sisi Siswa — "Kartu Digital Saya"

**Files:**
- Create: `app/Domains/Akademik/Actions/KartuSiswa/GetOrCreateKartuQrSiswaAction.php`
- Create: `app/Domains/Akademik/Actions/KartuSiswa/GenerateUlangKartuQrSiswaAction.php`
- Create: `app/Http/Controllers/Admin/KartuSayaController.php`
- Create: `resources/views/admin/siswa-akademik/kartu-saya.blade.php`
- Modify: `routes/admin/siswa-akademik.php`
- Modify: `resources/views/layouts/sidebar.blade.php`
- Test: `tests/Feature/Akademik/KartuSayaControllerTest.php`

**Interfaces:**
- Consumes: `KartuSiswa` model (Task 1).
- Produces: `GetOrCreateKartuQrSiswaAction::execute(Siswa $siswa): KartuSiswa`, `GenerateUlangKartuQrSiswaAction::execute(Siswa $siswa): KartuSiswa` — `GenerateUlangKartuQrSiswaAction` dipakai lagi Task 7 (tab admin).

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

use App\Domains\Akademik\Models\KartuSiswa;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;

function buatSiswaLoginUntukKartu(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    $user = User::factory()->create();
    $user->assignRole('siswa');
    $siswa->update(['person_id' => $siswa->person_id]);
    $siswaUser = $siswa->fresh();

    return [$siswaUser, $user];
}

it('membuat kartu QR otomatis saat siswa pertama kali buka halaman kartu saya', function () {
    [$siswa, $user] = buatSiswaLoginUntukKartu();
    $user->siswa()->save($siswa);

    $this->actingAs($user)->get(route('admin.kartu-saya.index'))->assertOk();

    expect(KartuSiswa::where('siswa_id', $siswa->id)->where('tipe', 'qr')->count())->toBe(1);
});

it('tidak membuat kartu baru kalau siswa sudah punya kartu aktif', function () {
    [$siswa, $user] = buatSiswaLoginUntukKartu();
    $user->siswa()->save($siswa);
    KartuSiswa::create(['siswa_id' => $siswa->id, 'tipe' => 'qr', 'kode' => 'kode-lama', 'is_active' => true]);

    $this->actingAs($user)->get(route('admin.kartu-saya.index'))->assertOk();

    expect(KartuSiswa::where('siswa_id', $siswa->id)->where('tipe', 'qr')->count())->toBe(1);
    expect(KartuSiswa::where('siswa_id', $siswa->id)->first()->kode)->toBe('kode-lama');
});

it('generate ulang mengganti kode kartu yang sama, bukan membuat baris baru', function () {
    [$siswa, $user] = buatSiswaLoginUntukKartu();
    $user->siswa()->save($siswa);
    $kartuLama = KartuSiswa::create(['siswa_id' => $siswa->id, 'tipe' => 'qr', 'kode' => 'kode-lama', 'is_active' => true]);

    $this->actingAs($user)->post(route('admin.kartu-saya.generate-ulang'))->assertRedirect();

    expect(KartuSiswa::where('siswa_id', $siswa->id)->where('tipe', 'qr')->count())->toBe(1);
    $kartuBaru = KartuSiswa::find($kartuLama->id);
    expect($kartuBaru->kode)->not->toBe('kode-lama');
});
```

> Catatan: sesuaikan cara relasi `User<->Siswa` di test di atas dengan skema autentikasi project ini kalau berbeda dari asumsi (`$user->siswa()`) — PERIKSA LANGSUNG `app/Models/User.php` relasi `siswa()` dan `app/Models/Siswa.php` relasi `user()` sebelum menulis test ini, ikuti pola PERSIS yang dipakai `tests/Feature/Guru/JurnalKbmControllerTest.php` atau test existing lain yang login sebagai siswa (`PresensiSayaControllerTest.php` kalau ada) untuk cara authenticate sebagai siswa yang benar di project ini.

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Feature/Akademik/KartuSayaControllerTest.php --compact`
Expected: FAIL — route `admin.kartu-saya.index` tidak ditemukan.

- [ ] **Step 3: Tulis Action get-or-create**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\KartuSiswa;

use App\Domains\Akademik\Models\KartuSiswa;
use App\Models\Siswa;
use Illuminate\Support\Str;

final class GetOrCreateKartuQrSiswaAction
{
    public function execute(Siswa $siswa): KartuSiswa
    {
        $kartu = KartuSiswa::where('siswa_id', $siswa->id)->where('tipe', 'qr')->first();

        if ($kartu !== null) {
            return $kartu;
        }

        return KartuSiswa::create([
            'siswa_id' => $siswa->id,
            'tipe' => 'qr',
            'kode' => Str::random(32),
            'is_active' => true,
        ]);
    }
}
```

- [ ] **Step 4: Tulis Action generate ulang**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\KartuSiswa;

use App\Domains\Akademik\Models\KartuSiswa;
use App\Models\Siswa;
use Illuminate\Support\Str;

final class GenerateUlangKartuQrSiswaAction
{
    public function __construct(
        private readonly GetOrCreateKartuQrSiswaAction $getOrCreateKartuQrSiswaAction,
    ) {}

    public function execute(Siswa $siswa): KartuSiswa
    {
        $kartu = $this->getOrCreateKartuQrSiswaAction->execute($siswa);

        $kartu->update(['kode' => Str::random(32), 'is_active' => true]);

        return $kartu->fresh();
    }
}
```

- [ ] **Step 5: Baca `routes/admin/siswa-akademik.php` yang ada, tambah 2 route baru**

Isi lengkap file setelah diubah:

```php
<?php

use App\Http\Controllers\Admin\JadwalPelajaranSiswaController;
use App\Http\Controllers\Admin\KartuSayaController;
use App\Http\Controllers\Admin\NilaiRaporSiswaController;
use App\Http\Controllers\Admin\PresensiSayaController;
use Illuminate\Support\Facades\Route;

Route::get('nilai-rapor-saya', [NilaiRaporSiswaController::class, 'index'])->name('nilai-rapor-saya.index');
Route::get('nilai-rapor-saya/unduh-rapor', [NilaiRaporSiswaController::class, 'unduhRapor'])->name('nilai-rapor-saya.unduh-rapor');
Route::get('jadwal-pelajaran-saya', [JadwalPelajaranSiswaController::class, 'index'])->name('jadwal-pelajaran-saya.index');
Route::get('presensi-saya', [PresensiSayaController::class, 'index'])->name('presensi-saya.index');
Route::get('kartu-saya', [KartuSayaController::class, 'index'])->name('kartu-saya.index');
Route::post('kartu-saya/generate-ulang', [KartuSayaController::class, 'generateUlang'])->name('kartu-saya.generate-ulang');
```

- [ ] **Step 6: Tulis Controller**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Actions\KartuSiswa\GenerateUlangKartuQrSiswaAction;
use App\Domains\Akademik\Actions\KartuSiswa\GetOrCreateKartuQrSiswaAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KartuSayaController extends Controller
{
    public function index(Request $request, GetOrCreateKartuQrSiswaAction $action): View
    {
        $siswa = $request->user()->siswa;
        abort_unless($siswa !== null, 403, 'Akun Anda tidak terhubung ke data siswa.');

        $kartu = $action->execute($siswa);

        return view('admin.siswa-akademik.kartu-saya', ['kartu' => $kartu]);
    }

    public function generateUlang(Request $request, GenerateUlangKartuQrSiswaAction $action): RedirectResponse
    {
        $siswa = $request->user()->siswa;
        abort_unless($siswa !== null, 403, 'Akun Anda tidak terhubung ke data siswa.');

        $action->execute($siswa);

        return redirect()->route('admin.kartu-saya.index')->with('status', 'Kode QR berhasil dibuat ulang. Kode lama sudah tidak berlaku.');
    }
}
```

- [ ] **Step 7: Tulis view**

```blade
<x-app-layout>
    <div class="mx-auto max-w-2xl space-y-4 px-4 sm:px-0">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Kartu Digital Saya</h1>
                <p class="text-xs text-gray-500 mt-0.5">Tunjukkan kode QR ini ke guru untuk presensi.</p>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 text-xs font-semibold text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex items-center justify-center py-4 sm:py-8">
            <div class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-6 text-center shadow-card space-y-5">
                <div class="flex flex-col items-center justify-center p-5 bg-gray-50/80 border border-gray-100 rounded-2xl">
                    {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::size(220)->generate($kartu->kode) !!}
                </div>

                <p class="text-xs text-gray-400 leading-relaxed">
                    Kode ini unik untuk Anda dan berlaku sampai Anda membuat kode baru.
                </p>

                <form method="POST" action="{{ route('admin.kartu-saya.generate-ulang') }}" class="pt-2 border-t border-gray-100" x-data
                    @submit.prevent="confirmDialog(
                        'Buat Ulang Kode QR?',
                        'Kode lama akan langsung tidak berlaku. Lanjutkan?',
                        { confirmLabel: 'Ya, Buat Ulang' }
                    ).then(confirmed => { if (confirmed) $el.submit() })"
                >
                    @csrf
                    <button type="submit" class="w-full inline-flex items-center justify-center rounded-xl bg-brand-600 px-5 py-2.5 text-xs font-semibold text-white shadow-sm transition hover:bg-brand-700">
                        Generate Ulang Kode QR
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 8: Tambah 1 item menu sidebar untuk siswa**

Baca `resources/views/layouts/sidebar.blade.php` di sekitar baris yang berisi `admin.presensi-saya.index`, tambah 1 baris baru persis setelahnya di array yang sama:

```php
Auth::user()->hasRole('siswa') ? ['route' => 'admin.kartu-saya.index', 'pattern' => 'admin.kartu-saya.*', 'label' => 'Kartu Digital Saya', 'icon' => 'qr-code'] : null,
```

(Kalau icon `qr-code` tidak ada di set icon yang dipakai project ini, cek icon lain yang tersedia dan relevan — PERIKSA `resources/views/components/icon.blade.php` atau setara untuk daftar icon valid sebelum commit.)

- [ ] **Step 9: Jalankan test, pastikan lulus**

Run: `php artisan test tests/Feature/Akademik/KartuSayaControllerTest.php --compact`
Expected: **3 passed**.

- [ ] **Step 10: Commit**

```bash
git add app/Domains/Akademik/Actions/KartuSiswa/GetOrCreateKartuQrSiswaAction.php app/Domains/Akademik/Actions/KartuSiswa/GenerateUlangKartuQrSiswaAction.php app/Http/Controllers/Admin/KartuSayaController.php resources/views/admin/siswa-akademik/kartu-saya.blade.php routes/admin/siswa-akademik.php resources/views/layouts/sidebar.blade.php tests/Feature/Akademik/KartuSayaControllerTest.php
git commit -m "feat(akademik): halaman Kartu Digital Saya utk siswa -- get-or-create & generate ulang kode QR

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 5: Sisi Guru — Endpoint Resolve Kartu

**Files:**
- Modify: `app/Http/Controllers/Guru/Akademik/JurnalKbmController.php`
- Modify: `routes/guru.php`
- Test: `tests/Feature/Guru/JurnalKbmResolveKartuTest.php`

**Interfaces:**
- Consumes: `ResolveKartuUntukPresensiAction::execute(string $kode, SesiPembelajaran $sesi, int $lembagaId): Siswa` (Task 3).
- Produces: `POST guru/jurnal-kbm/{sesi}/resolve-kartu` — 200 `{siswa_id, nama_lengkap}` atau 422 `{message}`. Dipakai Task 6 (frontend fetch).

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

use App\Domains\Akademik\Models\KartuSiswa;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;

it('resolve-kartu mengembalikan 200 dan data siswa untuk kode yang valid', function () {
    ['guruUser' => $guruUser, 'siswa' => $siswa] = siapkanGuruDenganJadwalHariIni();
    $this->actingAs($guruUser)->get(route('guru.jurnal-kbm.index'));
    $sesi = SesiPembelajaran::firstOrFail();
    KartuSiswa::create(['siswa_id' => $siswa->id, 'tipe' => 'qr', 'kode' => 'kode-guru-scan', 'is_active' => true]);

    $response = $this->actingAs($guruUser)->postJson(route('guru.jurnal-kbm.resolve-kartu', $sesi), ['kode' => 'kode-guru-scan']);

    $response->assertOk();
    $response->assertJson(['siswa_id' => $siswa->id, 'nama_lengkap' => $siswa->nama_lengkap]);
});

it('resolve-kartu mengembalikan 422 untuk kode yang tidak ditemukan', function () {
    ['guruUser' => $guruUser] = siapkanGuruDenganJadwalHariIni();
    $this->actingAs($guruUser)->get(route('guru.jurnal-kbm.index'));
    $sesi = SesiPembelajaran::firstOrFail();

    $response = $this->actingAs($guruUser)->postJson(route('guru.jurnal-kbm.resolve-kartu', $sesi), ['kode' => 'kode-tidak-ada']);

    $response->assertStatus(422);
    $response->assertJson(['message' => 'Kode kartu tidak valid atau sudah tidak aktif.']);
});

it('resolve-kartu mengembalikan 422 untuk siswa beda kelas dari sesi', function () {
    ['guruUser' => $guruUser] = siapkanGuruDenganJadwalHariIni();
    $this->actingAs($guruUser)->get(route('guru.jurnal-kbm.index'));
    $sesi = SesiPembelajaran::firstOrFail();
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasLain = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswaLain = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelasLain->id]);
    KartuSiswa::create(['siswa_id' => $siswaLain->id, 'tipe' => 'qr', 'kode' => 'kode-beda-kelas', 'is_active' => true]);

    $response = $this->actingAs($guruUser)->postJson(route('guru.jurnal-kbm.resolve-kartu', $sesi), ['kode' => 'kode-beda-kelas']);

    $response->assertStatus(422);
    $response->assertJson(['message' => 'Siswa ini tidak terdaftar di kelas untuk sesi ini.']);
});
```

> Catatan: `siapkanGuruDenganJadwalHariIni()` adalah helper yang SUDAH ADA dan dipakai `tests/Feature/Guru/JurnalKbmControllerTest.php` (dari plan A2 sebelumnya) — PERIKSA LANGSUNG isi helper itu (nama file tempat ia didefinisikan, kemungkinan `tests/Pest.php` atau di dalam file test itu sendiri) untuk pastikan bentuk array return-nya (`guruUser`, `siswa`, dst.) PERSIS seperti yang dipakai di sini sebelum menulis test ini. Kalau nama key beda, sesuaikan.

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Feature/Guru/JurnalKbmResolveKartuTest.php --compact`
Expected: FAIL — route `guru.jurnal-kbm.resolve-kartu` tidak ditemukan.

- [ ] **Step 3: Tambah route**

Di `routes/guru.php`, tambah baris ini setelah `jurnal-kbm.update`:

```php
Route::post('jurnal-kbm/{sesi}/resolve-kartu', [JurnalKbmController::class, 'resolveKartu'])->name('jurnal-kbm.resolve-kartu');
```

- [ ] **Step 4: Tambah method di controller**

Tambahkan ke `app/Http/Controllers/Guru/Akademik/JurnalKbmController.php`, setelah method `update()`:

```php
    public function resolveKartu(Request $request, SesiPembelajaran $sesi, \App\Domains\Akademik\Actions\KartuSiswa\ResolveKartuUntukPresensiAction $action): \Illuminate\Http\JsonResponse
    {
        $this->authorize('presensi.isi');
        $this->authorizeMilikGuru($sesi);

        $request->validate(['kode' => ['required', 'string']]);

        $guru = $request->user()->guru;
        abort_unless($guru !== null, 403);

        try {
            $siswa = $action->execute($request->string('kode')->toString(), $sesi, (int) $guru->lembaga_id);
        } catch (\App\Domains\Akademik\Exceptions\KartuValidasiException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['siswa_id' => $siswa->id, 'nama_lengkap' => $siswa->nama_lengkap]);
    }
```

Tambahkan juga `use` statement di bagian atas file:

```php
use App\Domains\Akademik\Actions\KartuSiswa\ResolveKartuUntukPresensiAction;
use App\Domains\Akademik\Exceptions\KartuValidasiException;
use Illuminate\Http\JsonResponse;
```

(lalu sederhanakan referensi fully-qualified di method jadi `ResolveKartuUntukPresensiAction $action` dan `JsonResponse` dan `KartuValidasiException` sesuai `use` yang baru ditambah — jangan biarkan FQCN inline kalau sudah ada `use`-nya, ikuti gaya file ini yang sudah pakai `use` di atas untuk class lain).

- [ ] **Step 5: Jalankan test, pastikan lulus**

Run: `php artisan test tests/Feature/Guru/JurnalKbmResolveKartuTest.php --compact`
Expected: **3 passed**.

- [ ] **Step 6: Jalankan ulang test JurnalKbmControllerTest existing, pastikan tidak regresi**

Run: `php artisan test tests/Feature/Guru/JurnalKbmControllerTest.php --compact`
Expected: semua test lama tetap **passed** (11 dari plan A2 sebelumnya), tidak ada perubahan ke method `show()`/`update()`.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Guru/Akademik/JurnalKbmController.php routes/guru.php tests/Feature/Guru/JurnalKbmResolveKartuTest.php
git commit -m "feat(akademik): endpoint resolve-kartu di JurnalKbmController utk validasi scan presensi

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 6: Sisi Guru — Tombol & Modal Scan di Jurnal KBM

**Files:**
- Modify: `resources/views/portals/guru/akademik/jurnal-kbm/show.blade.php`

**Interfaces:**
- Consumes: endpoint `POST guru/jurnal-kbm/{sesi}/resolve-kartu` (Task 5), component Alpine `qrCameraScanner()` dari `resources/js/qr-camera-scanner.js` (sudah ada, tidak diubah).

**PENTING — baca dulu struktur Alpine yang ada**: tiap baris `<tr x-data="{ status: '...' }">` di tabel presensi manual (baris ~70 file ini) punya scope Alpine SENDIRI-SENDIRI, TIDAK ada state global yang menyatukan semua baris. Supaya tombol scan (di luar tabel) bisa mengubah `status` satu baris tertentu, dipakai pola event bus Alpine standar: modal men-`$dispatch` custom event ke `window`, tiap baris punya listener `@presensi-scanned.window` yang cuma bereaksi kalau `siswaId` di detail event cocok dengan siswa baris itu. Ini TIDAK mengubah struktur `x-data` yang ada di tiap baris, cuma menambah 1 attribute listener.

- [ ] **Step 1: Tambah listener di tiap baris tabel presensi**

Di `resources/views/portals/guru/akademik/jurnal-kbm/show.blade.php`, ubah baris:

```blade
<tr x-data="{ status: '{{ $presensi->status->value }}' }" class="transition hover:bg-gray-50/50">
```

menjadi:

```blade
<tr
    x-data="{ status: '{{ $presensi->status->value }}' }"
    @presensi-scanned.window="if ($event.detail.siswaId === {{ $presensi->siswa_id }}) status = 'hadir'"
    class="transition hover:bg-gray-50/50"
>
```

- [ ] **Step 2: Tambah tombol "Scan Presensi" + modal, sebelum blok "Section 2: Presensi"**

Tambahkan blok ini persis sebelum baris `{{-- Section 2: Presensi --}}` (sebagai sibling `<div>` baru di dalam `<div class="p-6 space-y-6">`):

```blade
                    {{-- Scan Presensi via Kartu Digital --}}
                    <div
                        x-data="{
                            showModal: false,
                            pesan: null,
                            pesanTipe: 'success',
                            async kirimKode(kode) {
                                try {
                                    const response = await fetch('{{ route('guru.jurnal-kbm.resolve-kartu', $sesi) }}', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'Accept': 'application/json',
                                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content'),
                                        },
                                        body: JSON.stringify({ kode }),
                                    });
                                    const data = await response.json();
                                    if (response.ok) {
                                        this.pesan = data.nama_lengkap + ' berhasil dicatat Hadir.';
                                        this.pesanTipe = 'success';
                                        window.dispatchEvent(new CustomEvent('presensi-scanned', { detail: { siswaId: data.siswa_id } }));
                                    } else {
                                        this.pesan = data.message;
                                        this.pesanTipe = 'error';
                                    }
                                } catch (e) {
                                    this.pesan = 'Gagal menghubungi server. Periksa koneksi jaringan Anda.';
                                    this.pesanTipe = 'error';
                                }
                            }
                        }"
                        class="rounded-xl border border-gray-200 bg-gray-50/60 p-4"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-700">Scan Kartu Digital Siswa (opsional)</p>
                                <p class="text-xs text-gray-500 mt-0.5">Siswa yang scan otomatis tercatat Hadir. Siswa lain tetap diisi manual di tabel bawah.</p>
                            </div>
                            <button type="button" @click="showModal = true; pesan = null" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-brand-700">
                                Scan Presensi
                            </button>
                        </div>

                        <template x-if="pesan">
                            <div
                                class="mt-3 rounded-lg border p-3 text-xs font-semibold"
                                :class="pesanTipe === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800'"
                                x-text="pesan"
                            ></div>
                        </template>

                        {{-- Modal Kamera --}}
                        <div x-show="showModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
                            <div
                                x-data="qrCameraScanner({
                                    elementId: 'presensi-qr-reader',
                                    onScanSuccess: (decodedText) => { kirimKode(decodedText); },
                                    onCameraError: (msg) => { pesan = msg; pesanTipe = 'error'; }
                                })"
                                x-effect="showModal ? startCamera() : stopCamera()"
                                class="w-full max-w-sm rounded-2xl bg-white p-4 space-y-3"
                            >
                                <div class="flex items-center justify-between">
                                    <p class="text-sm font-bold text-gray-900">Scan Kartu Siswa</p>
                                    <button type="button" @click="showModal = false" class="text-gray-400 hover:text-gray-600">&times;</button>
                                </div>
                                <div class="relative mx-auto w-full overflow-hidden rounded-xl border border-gray-800 bg-gray-950">
                                    <div id="presensi-qr-reader" class="aspect-square w-full"></div>
                                </div>
                            </div>
                        </div>
                    </div>

```

- [ ] **Step 3: Pastikan asset `qr-camera-scanner.js` ter-import di bundle guru**

Baca `resources/js/app.js` (atau entry point JS utama project ini) untuk konfirmasi `qrCameraScanner` sudah didaftarkan sebagai Alpine component global (kemungkinan lewat `Alpine.data('qrCameraScanner', qrCameraScanner)` di file bootstrap Alpine, karena halaman `admin/kehadiran-sdm/scan.blade.php` sudah memakainya tanpa import eksplisit di file itu sendiri). Kalau ternyata BELUM terdaftar untuk halaman guru (mis. Alpine component di-load lewat build terpisah per-portal), tambahkan registrasi yang sama ke entry point yang relevan untuk portal guru. PERIKSA LANGSUNG sebelum asumsi — jangan duplikasi registrasi kalau sudah global.

- [ ] **Step 4: Build asset**

Run: `npm run build`
Expected: build sukses tanpa error, tidak ada warning "qrCameraScanner is not defined" saat halaman dibuka nanti di Step 6.

- [ ] **Step 5: Tulis feature test untuk bagian yang bisa diuji otomatis (render, bukan interaksi kamera)**

```php
<?php

it('halaman detail sesi jurnal kbm menampilkan tombol Scan Presensi', function () {
    ['guruUser' => $guruUser] = siapkanGuruDenganJadwalHariIni();
    $this->actingAs($guruUser)->get(route('guru.jurnal-kbm.index'));
    $sesi = \App\Domains\Akademik\Models\SesiPembelajaran::firstOrFail();

    $response = $this->actingAs($guruUser)->get(route('guru.jurnal-kbm.show', $sesi));

    $response->assertOk();
    $response->assertSee('Scan Presensi');
    $response->assertSee('presensi-qr-reader', false);
});
```

Tambahkan test ini ke `tests/Feature/Guru/JurnalKbmControllerTest.php` (file existing, JANGAN buat file baru).

Run: `php artisan test tests/Feature/Guru/JurnalKbmControllerTest.php --compact`
Expected: semua test (lama + 1 baru) **passed**.

> **CATATAN VERIFIKASI MANUAL WAJIB** (tidak bisa diotomasi lewat Pest — butuh browser + kamera nyata): setelah build asset, buka halaman Detail Sesi di browser sungguhan sebagai guru, klik "Scan Presensi", izinkan akses kamera, arahkan ke kode QR siswa (buka halaman "Kartu Digital Saya" siswa itu di device/tab lain untuk dapat kode QR-nya), pastikan: (a) modal kamera terbuka & video stream muncul, (b) scan berhasil menampilkan pesan sukses + radio status siswa itu otomatis berubah jadi "Hadir" di tabel di belakang modal, (c) scan siswa dari kelas/lembaga lain menampilkan pesan error yang sesuai tanpa mengubah status apa pun, (d) setelah modal ditutup, guru masih bisa mengubah status manual siswa yang sudah di-scan (field tidak terkunci), (e) klik "Simpan Jurnal & Presensi" tetap berfungsi normal dan memicu notifikasi WA (dari A2) untuk siswa yang status akhirnya Izin/Sakit/Alpa/Terlambat. Laporkan hasil verifikasi manual ini secara eksplisit di laporan task (bukan diam-diam diasumsikan lolos).

- [ ] **Step 6: Commit**

```bash
git add resources/views/portals/guru/akademik/jurnal-kbm/show.blade.php tests/Feature/Guru/JurnalKbmControllerTest.php
git commit -m "feat(akademik): tombol & modal Scan Presensi di halaman Jurnal KBM guru

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 7: Sisi Admin — Tab "Kartu Digital"

**Files:**
- Create: `app/Domains/Akademik/Actions/KartuSiswa/NonaktifkanKartuQrSiswaAction.php`
- Create: `resources/views/admin/siswa/tabs/kartu-digital.blade.php`
- Modify: `resources/views/admin/siswa/edit.blade.php`
- Modify: `app/Http/Controllers/Admin/SiswaController.php`
- Modify: `routes/admin/siswa.php`
- Test: `tests/Feature/Admin/SiswaKartuDigitalTabTest.php`

**Interfaces:**
- Consumes: `GetOrCreateKartuQrSiswaAction`, `GenerateUlangKartuQrSiswaAction` (Task 4).
- Produces: `NonaktifkanKartuQrSiswaAction::execute(Siswa $siswa): void`.

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

use App\Domains\Akademik\Models\KartuSiswa;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;

function buatAdminDenganPermission(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo('siswa.edit');

    return $user;
}

it('tab kartu digital tampil di halaman edit siswa', function () {
    $admin = buatAdminDenganPermission();
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    $response = $this->actingAs($admin)->get(route('admin.siswa.edit', $siswa));

    $response->assertOk();
    $response->assertSee('Kartu Digital');
});

it('admin bisa generate ulang kartu siswa dari tab admin', function () {
    $admin = buatAdminDenganPermission();
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    $kartuLama = KartuSiswa::create(['siswa_id' => $siswa->id, 'tipe' => 'qr', 'kode' => 'kode-lama-admin', 'is_active' => true]);

    $this->actingAs($admin)->post(route('admin.siswa.kartu-digital.generate-ulang', $siswa))->assertRedirect();

    expect(KartuSiswa::find($kartuLama->id)->kode)->not->toBe('kode-lama-admin');
});

it('admin bisa menonaktifkan kartu siswa dari tab admin', function () {
    $admin = buatAdminDenganPermission();
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    KartuSiswa::create(['siswa_id' => $siswa->id, 'tipe' => 'qr', 'kode' => 'kode-nonaktifkan', 'is_active' => true]);

    $this->actingAs($admin)->post(route('admin.siswa.kartu-digital.nonaktifkan', $siswa))->assertRedirect();

    expect(KartuSiswa::where('siswa_id', $siswa->id)->first()->is_active)->toBeFalse();
});
```

> Catatan: sesuaikan cara pemberian permission (`givePermissionTo`) dengan pola yang sudah dipakai test admin lain di project ini kalau berbeda — PERIKSA `tests/Feature/Admin/SiswaControllerTest.php` (kalau ada) untuk pola setup admin user yang benar.

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Feature/Admin/SiswaKartuDigitalTabTest.php --compact`
Expected: FAIL — view tidak menampilkan "Kartu Digital", route `admin.siswa.kartu-digital.*` tidak ditemukan.

- [ ] **Step 3: Tulis Action nonaktifkan**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\KartuSiswa;

use App\Domains\Akademik\Models\KartuSiswa;
use App\Models\Siswa;

final class NonaktifkanKartuQrSiswaAction
{
    public function execute(Siswa $siswa): void
    {
        KartuSiswa::where('siswa_id', $siswa->id)->where('tipe', 'qr')->update(['is_active' => false]);
    }
}
```

- [ ] **Step 4: Tambah route ke `routes/admin/siswa.php`**

Tambahkan baris ini di akhir file:

```php
Route::post('siswa/{siswa}/kartu-digital/generate-ulang', [SiswaController::class, 'generateUlangKartu'])->name('siswa.kartu-digital.generate-ulang');
Route::post('siswa/{siswa}/kartu-digital/nonaktifkan', [SiswaController::class, 'nonaktifkanKartu'])->name('siswa.kartu-digital.nonaktifkan');
```

- [ ] **Step 5: Tambah 2 method ke `app/Http/Controllers/Admin/SiswaController.php`**

Tambahkan di akhir class (sebelum kurung kurawal penutup), dan tambahkan `use` statement yang relevan di bagian atas file:

```php
use App\Domains\Akademik\Actions\KartuSiswa\GenerateUlangKartuQrSiswaAction;
use App\Domains\Akademik\Actions\KartuSiswa\NonaktifkanKartuQrSiswaAction;
```

```php
    public function generateUlangKartu(Siswa $siswa, GenerateUlangKartuQrSiswaAction $action): RedirectResponse
    {
        $this->authorize('siswa.edit');

        $action->execute($siswa);

        return redirect()->route('admin.siswa.edit', $siswa)->with('status', 'Kode QR siswa berhasil dibuat ulang.');
    }

    public function nonaktifkanKartu(Siswa $siswa, NonaktifkanKartuQrSiswaAction $action): RedirectResponse
    {
        $this->authorize('siswa.edit');

        $action->execute($siswa);

        return redirect()->route('admin.siswa.edit', $siswa)->with('status', 'Kartu digital siswa berhasil dinonaktifkan.');
    }
```

- [ ] **Step 6: Tulis view tab**

```blade
<div x-show="activeTab === 'kartu-digital'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
    <div class="rounded-2xl border border-gray-200/80 bg-white p-6 shadow-card space-y-5">
        <div>
            <h3 class="font-semibold text-gray-900">Kartu Digital Siswa</h3>
            <p class="text-xs text-gray-400">Kode QR untuk presensi via scan di Jurnal KBM.</p>
        </div>

        @php $kartu = $siswa->kartuSiswa()->where('tipe', 'qr')->first(); @endphp

        @if ($kartu)
            <dl class="divide-y divide-gray-100 text-sm">
                <div class="flex justify-between py-2.5">
                    <dt class="text-gray-500">Status</dt>
                    <dd>
                        <x-badge tone="{{ $kartu->is_active ? 'green' : 'amber' }}">{{ $kartu->is_active ? 'Aktif' : 'Non-aktif' }}</x-badge>
                    </dd>
                </div>
                <div class="flex justify-between py-2.5">
                    <dt class="text-gray-500">Kode</dt>
                    <dd class="font-mono text-xs text-gray-900">{{ substr($kartu->kode, 0, 8) }}...</dd>
                </div>
                <div class="flex justify-between py-2.5">
                    <dt class="text-gray-500">Dibuat</dt>
                    <dd class="text-gray-900">{{ $kartu->created_at->format('d F Y') }}</dd>
                </div>
            </dl>

            <div class="flex gap-3 pt-2 border-t border-gray-100">
                @can('siswa.edit')
                    <form method="POST" action="{{ route('admin.siswa.kartu-digital.generate-ulang', $siswa) }}">
                        @csrf
                        <button type="submit" class="rounded-lg border border-gray-200 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                            Generate Ulang
                        </button>
                    </form>
                    @if ($kartu->is_active)
                        <form method="POST" action="{{ route('admin.siswa.kartu-digital.nonaktifkan', $siswa) }}">
                            @csrf
                            <button type="submit" class="rounded-lg border border-rose-200 bg-white px-3.5 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50">
                                Nonaktifkan
                            </button>
                        </form>
                    @endif
                @endcan
            </div>
        @else
            <p class="text-sm text-gray-500">Siswa belum pernah membuka halaman Kartu Digital Saya, jadi kartu belum ada.</p>
        @endif
    </div>
</div>
```

Tambahkan relasi `kartuSiswa()` ke `App\Models\Siswa` (kalau belum ada) — baca dulu file itu, tambahkan setelah relasi `kelas()`:

```php
    public function kartuSiswa(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Domains\Akademik\Models\KartuSiswa::class);
    }
```

- [ ] **Step 7: Modifikasi `resources/views/admin/siswa/edit.blade.php`**

Tambahkan 1 tombol tab baru setelah tombol tab "Keringanan" (di dalam blok "Navigation Tabs Header"):

```blade
                <button type="button" @click="activeTab = 'kartu-digital'" :class="activeTab === 'kartu-digital' ? 'border-brand-600 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="flex items-center gap-2 border-b-2 py-3 px-4 transition whitespace-nowrap">
                    <x-icon name="qr_code" class="h-4 w-4" />
                    <span>Kartu Digital</span>
                </button>
```

Tambahkan `@include` baru setelah `@include('admin.siswa.tabs.keringanan')`:

```blade
            @include('admin.siswa.tabs.kartu-digital')
```

(Kalau icon `qr_code` tidak ada di set icon component project ini, cek icon lain yang tersedia dan relevan sebelum commit — sama seperti catatan Task 4 Step 8.)

- [ ] **Step 8: Jalankan test, pastikan lulus**

Run: `php artisan test tests/Feature/Admin/SiswaKartuDigitalTabTest.php --compact`
Expected: **3 passed**.

- [ ] **Step 9: Jalankan ulang test Siswa admin existing (kalau ada), pastikan tidak regresi**

Run: `php artisan test tests/Feature/Admin --compact --filter=Siswa`
Expected: semua test terkait Siswa admin tetap **passed**.

- [ ] **Step 10: Commit**

```bash
git add app/Domains/Akademik/Actions/KartuSiswa/NonaktifkanKartuQrSiswaAction.php resources/views/admin/siswa/tabs/kartu-digital.blade.php resources/views/admin/siswa/edit.blade.php app/Http/Controllers/Admin/SiswaController.php routes/admin/siswa.php app/Models/Siswa.php tests/Feature/Admin/SiswaKartuDigitalTabTest.php
git commit -m "feat(akademik): tab Kartu Digital di halaman Data Siswa admin -- generate ulang & nonaktifkan

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 8: Penutup — Full Test Suite & Dokumentasi

**Files:**
- Create: `.agents/logs/2026-09-06-kartu-digital-siswa-presensi-scan.md`
- Modify: `PETA_PENGEMBANGAN.md`

- [ ] **Step 1: Pastikan tidak ada proses test lain berjalan**

Run (PowerShell): `Get-CimInstance Win32_Process -Filter "Name='php.exe'" | Select ProcessId,CommandLine`

- [ ] **Step 2: Full test suite**

Run: `php artisan test --compact`
Expected: 0 failure baru dibanding baseline sebelum plan ini (baseline: 2854 passed / 4 failed pre-existing tidak terkait, dari penutup plan A2 — bandingkan angka `passed` naik sejumlah test baru plan ini, dan 4 failure lama tetap sama, bukan bertambah).

- [ ] **Step 3: Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `{"tool":"pint","result":"passed"}` atau `"fixed"` (commit terpisah kalau ada auto-fix).

- [ ] **Step 4: Tulis handoff log**

Tulis `.agents/logs/2026-09-06-kartu-digital-siswa-presensi-scan.md` — ringkas apa yang dibangun per task, commit hash, hasil test, dan CATATAN VERIFIKASI MANUAL Task 6 (sudah dilakukan atau belum, dan hasilnya apa).

- [ ] **Step 5: Update `PETA_PENGEMBANGAN.md`**

Cari baris roadmap "Kartu pelajar digital (QR)" (sekitar §Ruang Siswa, ditandai "Belum Ada, prioritas Rendah") — update jadi mencerminkan status yang BENAR: kode QR permanen + presensi via scan (Opsi A3) SUDAH ADA, tapi RFID, lookup VA di loket Keuangan, dan cetak kartu fisik massal TETAP Belum Ada (di luar cakupan). Jangan klaim lebih dari yang benar-benar dibangun.

- [ ] **Step 6: Commit**

```bash
git add .agents/logs/2026-09-06-kartu-digital-siswa-presensi-scan.md PETA_PENGEMBANGAN.md
git commit -m "docs(akademik): handoff log & update roadmap -- kartu digital siswa & presensi via scan selesai

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Self-Review

**1. Cakupan spec**: §2 (15 keputusan desain) semua tercermin — poin 1 (Task 5-6 tidak sentuh A2), poin 4-5-6 (Task 1, kolom `tipe` enum + `resolveSiswa()`), poin 10 (Task 4, `simplesoftwareio/simple-qrcode` bukan API eksternal, tanpa dependency npm baru), poin 11 (Task 7, tab bukan halaman baru), poin 12 (Task 6, override + field tetap editable), poin 13 (idempoten by design karena state di browser). §3.1-3.4 masing-masing dapat task sendiri (Task 1 / Task 4 / Task 5-6 / Task 7). §4 (8 skenario test) tercakup di Task 1 (3), Task 3 (4 var 5&6 tergabung sbg 1 action), Task 4 (get-or-create+generate ulang), regresi Task 5-6.

**2. Placeholder scan**: semua step berisi kode lengkap. Beberapa step menyertakan instruksi "PERIKSA LANGSUNG dulu" untuk hal yang genuinely tidak bisa dipastikan tanpa membaca kode aktual saat eksekusi (nama icon component, isi helper test existing, registrasi Alpine component) — ini BUKAN placeholder, ini instruksi verifikasi eksplisit yang tetap actionable, konsisten dengan disiplin "verify before writing" yang dipakai across plan A2 sebelumnya.

**3. Konsistensi tipe**: `KartuSiswa::resolveSiswa(string $kode): ?Siswa` (Task 1) dipakai identik di `ResolveKartuUntukPresensiAction` (Task 3). `GetOrCreateKartuQrSiswaAction::execute(Siswa $siswa): KartuSiswa` (Task 4) dipakai identik sbg dependency `GenerateUlangKartuQrSiswaAction` (Task 4) dan lagi di admin controller (Task 7). `ResolveKartuUntukPresensiAction::execute(string $kode, SesiPembelajaran $sesi, int $lembagaId): Siswa` (Task 3) dipakai identik di endpoint controller (Task 5). Ditemukan & DIPERBAIKI saat menulis plan ini: desain awal "generate ulang = nonaktifkan lama + buat baris baru" akan melanggar unique constraint `(siswa_id, tipe)` dari Task 1 — diganti jadi "generate ulang = update kolom `kode` pada baris yang sama", konsisten di Task 4 dan Task 7.


