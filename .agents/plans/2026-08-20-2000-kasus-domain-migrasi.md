# Migrasi Domain Kasus Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrasikan modul Pendampingan/Kasus (10 controller, 1042 baris, 6 model) ke `app/Domains/Kasus/` — domain BARU sepenuhnya, mengikuti standar `.agents/skills/laravel-feature-standard/SKILL.md`.

**Architecture:** Controller thin (validasi → DTO → Action/Policy → response). 6 model + 3 enum + 1 DTO pindah ke `Domains\Kasus\`. 2 service lama pindah dari `app/Services/`. `KasusPolicy` BARU mengonsolidasi 4 titik duplikasi otorisasi. 11 view pindah ke `portals/kasus/` (tanpa scope, lintas-role) dan `portals/lembaga/kasus/` (admin-only).

**Tech Stack:** Laravel 11, Pest, pola Action/DTO dari `Domains\Akademik\Actions\Jadwal\CreateJadwalPelajaranAction` sebagai referensi.

## Global Constraints

- **Zero-behavior-change untuk SEMUA bagian KECUALI konsolidasi `KasusPolicy`** — dan bahkan `KasusPolicy` HARUS menghasilkan keputusan izin/tolak identik untuk tiap kombinasi role yang sudah ada, cuma lokasi kodenya disatukan.
- Setiap query `withoutGlobalScope(TenantScope::class)` yang ada SEKARANG di kode asli WAJIB dipertahankan persis di lokasi barunya, termasuk komentar penjelasannya — JANGAN dihapus sebagai "housekeeping".
- `Route::bind('kasus', ...)` di `routes/kasus.php` WAJIB dipertahankan (cuma update namespace `Kasus` di baris query). Action/Policy TIDAK PERNAH query ulang `Kasus` dengan asumsi TenantScope normal — selalu terima objek yang sudah di-resolve dari route binding.
- Model pindahan (`Kasus`, `KasusConsent`, `KasusSesi`, `KasusTugas`, `KasusTugasSubmission`, `KasusEvaluasi`) HANYA `$fillable`/`casts()`/relationship/`LogsActivity` config — tidak ada business logic baru.
- Notification & Mail class TETAP di `app/Notifications/` dan `app/Mail/` — TIDAK dipindah.
- Controller namespace (`Admin\KasusController`, `KasusController` top-level, dst) TIDAK dipindah — tetap di lokasi sekarang.
- View WAJIB ikut pindah bersamaan dengan task controllernya (bukan ditunda) ke `portals/kasus/` (lintas-role, tanpa scope) atau `portals/lembaga/kasus/` (admin-only).
- **Bahaya cari-ganti dot-notation**: `view(`/`@include(` beda dari `route(` meski sering satu prefix sama. HANYA `resources/views/kasus/show.blade.php` yang punya `@include('kasus.partials._tab-xxx')` (4 baris) yang perlu diubah — SEMUA `route('kasus.xxx', ...)` dan `route('admin.kasus.xxx', ...)` di seluruh view TIDAK BOLEH disentuh. Verifikasi wajib tiap task view: `grep -rn "route('portals\." resources/views/portals/kasus resources/views/portals/lembaga/kasus` harus kosong.
- Testing: scoped per task. Full suite HANYA di task terakhir, minta izin eksplisit user dulu.
- Baseline kode: commit `a292003`, branch `rbac-v2`. Verifikasi ulang isi file kalau ada commit baru sebelum eksekusi.

---

## Konteks Umum

`Kasus` (model inti) dipakai di 59 file, tapi HAMPIR SEMUA adalah notification/mail/test/factory/seeder milik ekosistem Kasus sendiri — HANYA `app/Http/Controllers/Admin/DashboardController.php` dan `app/Http/Controllers/Concerns/AssertsKonselorPemegangKasus.php` yang di luar 10 controller inti. Model lain (`KasusConsent`, `KasusSesi`, `KasusTugas`, `KasusTugasSubmission`, `KasusEvaluasi`) 0 pemakai di luar ekosistemnya sendiri. `StatusKasus` dipakai 31 file (semua Kasus-internal), `StatusKasusSesi`/`StatusKasusTugas` 6 file, `KonselorKandidat` DTO cuma dipakai `KonselorAllocationResolver`.

Cari SEMUA file di atas dengan pencarian teks (`grep`) sebelum tiap task, JANGAN hardcode daftar dari plan ini kalau sudah lama berlalu sejak baseline commit.

---

## Task 1: Pindahkan 6 Model ke `Domains\Kasus\Models\`

**Files:**
- Move: `app/Models/{Kasus,KasusConsent,KasusSesi,KasusTugas,KasusTugasSubmission,KasusEvaluasi}.php` → `app/Domains/Kasus/Models/`
- Modify: SEMUA file yang meng-`use App\Models\{Kasus,KasusConsent,...}` — cari lewat `grep -rln "^use App\\\\Models\\\\Kasus" --include="*.php" app database tests routes` (hasil grep 2026-08-20: lihat daftar 59+11+13+18+12+8 file dari riwayat brainstorming; JALANKAN ULANG grep-nya sendiri, jangan percaya angka lama)
- Modify: `app/Http/Controllers/Admin/DashboardController.php`, `app/Http/Controllers/Concerns/AssertsKonselorPemegangKasus.php` (dua titik non-ekosistem)
- Modify: `routes/kasus.php` (baris `Route::bind('kasus', ...)`)

**Interfaces:**
- Produces: `App\Domains\Kasus\Models\{Kasus,KasusConsent,KasusSesi,KasusTugas,KasusTugasSubmission,KasusEvaluasi}` — dipakai semua task berikutnya

- [ ] **Step 1: Pindahkan 6 file model secara fisik**

```bash
mkdir -p app/Domains/Kasus/Models
git mv app/Models/Kasus.php app/Domains/Kasus/Models/Kasus.php
git mv app/Models/KasusConsent.php app/Domains/Kasus/Models/KasusConsent.php
git mv app/Models/KasusSesi.php app/Domains/Kasus/Models/KasusSesi.php
git mv app/Models/KasusTugas.php app/Domains/Kasus/Models/KasusTugas.php
git mv app/Models/KasusTugasSubmission.php app/Domains/Kasus/Models/KasusTugasSubmission.php
git mv app/Models/KasusEvaluasi.php app/Domains/Kasus/Models/KasusEvaluasi.php
```

- [ ] **Step 2: Ubah namespace & tambah `newFactory()` di tiap model**

`app/Domains/Kasus/Models/Kasus.php` — ganti baris `namespace App\Models;` jadi `namespace App\Domains\Kasus\Models;`, tambah `use App\Models\{Siswa,Guru,Karyawan,OrangTua};` untuk relasi lintas-domain, tambah `use Database\Factories\KasusFactory;` dan method `newFactory()`. Hasil akhir:

```php
<?php

namespace App\Domains\Kasus\Models;

use App\Enums\StatusKasus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\OrangTua;
use App\Models\Siswa;
use Database\Factories\KasusFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Kasus extends Model
{
    use HasFactory, BelongsToTenant, LogsActivity, SoftDeletes;

    protected static function newFactory(): KasusFactory
    {
        return KasusFactory::new();
    }

    protected $table = 'kasus';

    protected $fillable = [
        'siswa_id', 'lembaga_id', 'diajukan_oleh_guru_id', 'diajukan_oleh_orang_tua_id',
        'kategori_masalah', 'deskripsi', 'lampiran', 'tingkat_urgensi', 'status',
        'konselor_guru_id', 'konselor_karyawan_id', 'dikonfirmasi_pihak_lain_at',
    ];

    protected $attributes = [
        'status' => 'diajukan',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusKasus::class,
            'dikonfirmasi_pihak_lain_at' => 'datetime',
        ];
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function diajukanOlehGuru(): BelongsTo
    {
        return $this->belongsTo(Guru::class, 'diajukan_oleh_guru_id');
    }

    public function diajukanOlehOrangTua(): BelongsTo
    {
        return $this->belongsTo(OrangTua::class, 'diajukan_oleh_orang_tua_id');
    }

    public function konselorGuru(): BelongsTo
    {
        return $this->belongsTo(Guru::class, 'konselor_guru_id');
    }

    public function konselorKaryawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'konselor_karyawan_id');
    }

    public function consents(): HasMany
    {
        return $this->hasMany(KasusConsent::class);
    }

    public function sesi(): HasMany
    {
        return $this->hasMany(KasusSesi::class);
    }

    public function tugas(): HasMany
    {
        return $this->hasMany(KasusTugas::class);
    }

    public function evaluasi(): HasMany
    {
        return $this->hasMany(KasusEvaluasi::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'konselor_guru_id', 'konselor_karyawan_id'])
            ->logOnlyDirty()
            ->useLogName('kasus');
    }
}
```

`app/Domains/Kasus/Models/KasusConsent.php`:

```php
<?php

namespace App\Domains\Kasus\Models;

use Database\Factories\KasusConsentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class KasusConsent extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory(): KasusConsentFactory
    {
        return KasusConsentFactory::new();
    }

    protected $table = 'kasus_consent';

    protected $fillable = ['kasus_id', 'jenis', 'status', 'disetujui_at'];

    protected function casts(): array
    {
        return [
            'disetujui_at' => 'datetime',
        ];
    }

    public function kasus(): BelongsTo
    {
        return $this->belongsTo(Kasus::class);
    }
}
```

`app/Domains/Kasus/Models/KasusSesi.php`:

```php
<?php

namespace App\Domains\Kasus\Models;

use App\Enums\StatusKasusSesi;
use Database\Factories\KasusSesiFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class KasusSesi extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected static function newFactory(): KasusSesiFactory
    {
        return KasusSesiFactory::new();
    }

    protected $table = 'kasus_sesi';

    protected $fillable = [
        'kasus_id', 'dijadwalkan_pada', 'peserta', 'lokasi_mode',
        'status', 'alasan_batal', 'catatan_internal',
    ];

    protected $attributes = [
        'status' => 'terjadwal',
    ];

    protected function casts(): array
    {
        return [
            'dijadwalkan_pada' => 'datetime',
            'status' => StatusKasusSesi::class,
        ];
    }

    public function kasus(): BelongsTo
    {
        return $this->belongsTo(Kasus::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status'])
            ->logOnlyDirty()
            ->useLogName('kasus_sesi');
    }
}
```

`app/Domains/Kasus/Models/KasusTugas.php`:

```php
<?php

namespace App\Domains\Kasus\Models;

use App\Enums\StatusKasusTugas;
use Database\Factories\KasusTugasFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class KasusTugas extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected static function newFactory(): KasusTugasFactory
    {
        return KasusTugasFactory::new();
    }

    protected $table = 'kasus_tugas';

    protected $fillable = [
        'kasus_id', 'judul', 'instruksi', 'frekuensi',
        'batch_id', 'batch_urutan', 'batch_total',
        'mulai_pada', 'batas_selesai_pada', 'status',
    ];

    protected $attributes = [
        'status' => 'ditugaskan',
    ];

    protected function casts(): array
    {
        return [
            'mulai_pada' => 'date',
            'batas_selesai_pada' => 'date',
            'status' => StatusKasusTugas::class,
        ];
    }

    public function kasus(): BelongsTo
    {
        return $this->belongsTo(Kasus::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(KasusTugasSubmission::class, 'tugas_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status'])
            ->logOnlyDirty()
            ->useLogName('kasus_tugas');
    }
}
```

`app/Domains/Kasus/Models/KasusTugasSubmission.php` (perhatikan `StatusKasusTugas::Ditugaskan`/`::Dikerjakan` di `booted()` — sudah FQCN penuh di kode asli, TETAP dipertahankan sama, cuma pastikan `App\Enums\StatusKasusTugas` tidak ikut pindah — lihat Task 2):

```php
<?php

namespace App\Domains\Kasus\Models;

use App\Models\OrangTua;
use App\Models\Siswa;
use Database\Factories\KasusTugasSubmissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class KasusTugasSubmission extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory(): KasusTugasSubmissionFactory
    {
        return KasusTugasSubmissionFactory::new();
    }

    protected $table = 'kasus_tugas_submission';

    protected $fillable = [
        'tugas_id', 'siswa_id', 'orang_tua_id', 'teks', 'lampiran',
        'status_review', 'catatan_revisi',
    ];

    protected $attributes = [
        'status_review' => 'menunggu_review',
    ];

    protected static function booted(): void
    {
        static::created(function (KasusTugasSubmission $submission) {
            $tugas = $submission->tugas;

            if ($tugas->status === \App\Enums\StatusKasusTugas::Ditugaskan) {
                $tugas->update(['status' => \App\Enums\StatusKasusTugas::Dikerjakan]);
            }
        });
    }

    public function tugas(): BelongsTo
    {
        return $this->belongsTo(KasusTugas::class, 'tugas_id');
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function orangTua(): BelongsTo
    {
        return $this->belongsTo(OrangTua::class, 'orang_tua_id');
    }
}
```

`app/Domains/Kasus/Models/KasusEvaluasi.php`:

```php
<?php

namespace App\Domains\Kasus\Models;

use App\Models\User;
use Database\Factories\KasusEvaluasiFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class KasusEvaluasi extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory(): KasusEvaluasiFactory
    {
        return KasusEvaluasiFactory::new();
    }

    protected $table = 'kasus_evaluasi';

    protected $fillable = ['kasus_id', 'tanggal', 'catatan', 'keputusan', 'dibuat_oleh_user_id'];

    protected function casts(): array
    {
        return [
            'tanggal' => 'datetime',
        ];
    }

    public function kasus(): BelongsTo
    {
        return $this->belongsTo(Kasus::class);
    }

    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh_user_id');
    }
}
```

- [ ] **Step 3: Update `use` di 6 factory (`database/factories/Kasus*Factory.php`)**

Di tiap file, ganti `use App\Models\Kasus...;` jadi `use App\Domains\Kasus\Models\Kasus...;`. Kalau factory punya `protected $model = ...` eksplisit, update juga.

- [ ] **Step 4: Update SEMUA file lain hasil grep (controllers, tests, seeder, notifications, mail, console commands)**

Jalankan:
```bash
grep -rln "^use App\\\\Models\\\\Kasus;" --include="*.php" app database tests
grep -rln "^use App\\\\Models\\\\KasusConsent;" --include="*.php" app database tests
grep -rln "^use App\\\\Models\\\\KasusSesi;" --include="*.php" app database tests
grep -rln "^use App\\\\Models\\\\KasusTugas;$" --include="*.php" app database tests
grep -rln "^use App\\\\Models\\\\KasusTugasSubmission;" --include="*.php" app database tests
grep -rln "^use App\\\\Models\\\\KasusEvaluasi;" --include="*.php" app database tests
```
Di TIAP file hasil, ganti baris `use App\Models\Kasus...;` jadi `use App\Domains\Kasus\Models\Kasus...;` (biarkan class lain di file yang sama, kalau ada, tetap `App\Models\`).

- [ ] **Step 5: Update `routes/kasus.php`**

Cari baris:
```php
Route::bind('kasus', function ($value) {
    return \App\Models\Kasus::withoutGlobalScope(\App\Models\Scopes\TenantScope::class)
        ->withTrashed()
        ->findOrFail($value);
});
```
Ganti jadi:
```php
Route::bind('kasus', function ($value) {
    return \App\Domains\Kasus\Models\Kasus::withoutGlobalScope(\App\Models\Scopes\TenantScope::class)
        ->withTrashed()
        ->findOrFail($value);
});
```
Komentar di atasnya (penjelasan orang tua tanpa lembaga_id) TETAP, tidak dihapus.

- [ ] **Step 6: Cari referensi implisit same-namespace yang lolos dari grep `use`-line**

Sesuai pelajaran Sub-Task 05 (`JadwalPelajaran.php` mereferensikan `JamPelajaran::class` tanpa `use` karena dulu 1 namespace) — jalankan test scoped luas (Step 7) dan CEK setiap error "Class not found"/"Class... not found" untuk model 6 ini. Titik yang WAJIB dicek manual: `app/Http/Controllers/Concerns/AssertsKonselorPemegangKasus.php` (constructor param `Kasus $kasus` — pastikan sudah punya `use App\Domains\Kasus\Models\Kasus;` di atasnya).

- [ ] **Step 7: Jalankan test scoped luas**

Run: `php artisan test tests/Feature/Kasus*.php tests/Feature/Admin/Kasus*.php tests/Feature/DashboardKasus*.php tests/Feature/Console/KirimReminderSesiTest.php tests/Feature/Console/TandaiTugasTerlewatTest.php tests/Unit/Enums/StatusKasusTest.php`
Expected: semua PASS. Kalau ada "Class not found", lacak file yang disebut, tambahkan `use` yang kurang (lihat Step 6).

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "refactor(kasus): pindah 6 model ke Domains\Kasus\Models, update semua consumer"
```

---

## Task 2: Pindahkan 3 Enum & 1 DTO ke Domain

**Files:**
- Move: `app/Enums/{StatusKasus,StatusKasusSesi,StatusKasusTugas}.php` → `app/Domains/Kasus/Enums/`
- Move: `app/DataTransferObjects/KonselorKandidat.php` → `app/Domains/Kasus/DataTransferObjects/KonselorKandidat.php`
- Modify: semua consumer (hasil grep, minimal 6 model dari Task 1, 10 controller, `app/Services/KonselorAllocationResolver.php`, dan test files)

**Interfaces:**
- Consumes: tidak ada
- Produces: `App\Domains\Kasus\Enums\{StatusKasus,StatusKasusSesi,StatusKasusTugas}`, `App\Domains\Kasus\DataTransferObjects\KonselorKandidat` — dipakai Task 1 (model, sudah diupdate di Task 1 tapi perlu diverifikasi ulang di sini karena Task 1 mendahului Task 2 secara urutan file) dan Task 3, 5-11

**PENTING urutan:** Task 1 sudah membuat model dengan `use App\Enums\StatusKasus;` (BELUM dipindah saat itu). Task ini mengupdate baris itu jadi `use App\Domains\Kasus\Enums\StatusKasus;` di ke-3 model yang memakainya (`Kasus`, `KasusSesi`, `KasusTugas`) + `KasusTugasSubmission` (FQCN inline di `booted()`).

- [ ] **Step 1: Baca isi asli 3 enum & 1 DTO untuk memastikan tidak ada perubahan logic saat dipindah**

Jalankan `cat app/Enums/StatusKasus.php app/Enums/StatusKasusSesi.php app/Enums/StatusKasusTugas.php app/DataTransferObjects/KonselorKandidat.php` dan simpan isinya — pindahkan APA ADANYA, cuma ganti baris `namespace`.

- [ ] **Step 2: Pindahkan file secara fisik**

```bash
mkdir -p app/Domains/Kasus/Enums app/Domains/Kasus/DataTransferObjects
git mv app/Enums/StatusKasus.php app/Domains/Kasus/Enums/StatusKasus.php
git mv app/Enums/StatusKasusSesi.php app/Domains/Kasus/Enums/StatusKasusSesi.php
git mv app/Enums/StatusKasusTugas.php app/Domains/Kasus/Enums/StatusKasusTugas.php
git mv app/DataTransferObjects/KonselorKandidat.php app/Domains/Kasus/DataTransferObjects/KonselorKandidat.php
```

- [ ] **Step 3: Ubah baris `namespace` di keempat file (isi lain TIDAK berubah)**

`app/Domains/Kasus/Enums/StatusKasus.php` — baris pertama jadi `namespace App\Domains\Kasus\Enums;`
`app/Domains/Kasus/Enums/StatusKasusSesi.php` — sama
`app/Domains/Kasus/Enums/StatusKasusTugas.php` — sama
`app/Domains/Kasus/DataTransferObjects/KonselorKandidat.php` — baris pertama jadi `namespace App\Domains\Kasus\DataTransferObjects;`, dan kalau ada `use App\Models\Guru;`/`use App\Models\Karyawan;` di dalamnya (untuk type-hint property `model`), itu TETAP `App\Models\` (Guru/Karyawan tidak pindah).

- [ ] **Step 4: Update SEMUA consumer**

```bash
grep -rln "^use App\\\\Enums\\\\StatusKasus;" --include="*.php" app database tests
grep -rln "^use App\\\\Enums\\\\StatusKasusSesi;" --include="*.php" app database tests
grep -rln "^use App\\\\Enums\\\\StatusKasusTugas;" --include="*.php" app database tests
grep -rln "^use App\\\\DataTransferObjects\\\\KonselorKandidat;" --include="*.php" app database tests
```
Update tiap baris `use` yang ditemukan. KHUSUS `app/Domains/Kasus/Models/KasusTugasSubmission.php`: FQCN inline `\App\Enums\StatusKasusTugas::Ditugaskan`/`::Dikerjakan` di method `booted()` (2 kemunculan) diganti jadi `\App\Domains\Kasus\Enums\StatusKasusTugas::Ditugaskan`/`::Dikerjakan`.

- [ ] **Step 5: Jalankan test scoped**

Run: `php artisan test tests/Unit/Enums/StatusKasusTest.php tests/Feature/Kasus*.php tests/Feature/Admin/Kasus*.php`
Expected: semua PASS.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "refactor(kasus): pindah 3 enum & KonselorKandidat DTO ke Domains\Kasus"
```

---

## Task 3: Pindahkan 2 Service ke `Domains\Kasus\Services\`

**Files:**
- Move: `app/Services/KonselorAllocationResolver.php` → `app/Domains/Kasus/Services/KonselorAllocationResolver.php`
- Move: `app/Services/TugasBatchGenerator.php` → `app/Domains/Kasus/Services/TugasBatchGenerator.php`
- Modify: `app/Http/Controllers/Admin/KasusController.php`, `app/Http/Controllers/KasusTugasController.php`, `app/Http/Controllers/KasusTugasBatchPreviewController.php`, test files pemakai kedua service ini

**Interfaces:**
- Consumes: `App\Domains\Kasus\DataTransferObjects\KonselorKandidat` (Task 2)
- Produces: `App\Domains\Kasus\Services\{KonselorAllocationResolver,TugasBatchGenerator}` — dipakai Task 5 (Manajemen) dan Task 9 (Tugas)

- [ ] **Step 1: Pindahkan file, ubah namespace & import**

```bash
mkdir -p app/Domains/Kasus/Services
git mv app/Services/KonselorAllocationResolver.php app/Domains/Kasus/Services/KonselorAllocationResolver.php
git mv app/Services/TugasBatchGenerator.php app/Domains/Kasus/Services/TugasBatchGenerator.php
```

`app/Domains/Kasus/Services/KonselorAllocationResolver.php` — isi lengkap (namespace + import DTO diupdate, logic SAMA PERSIS):

```php
<?php

namespace App\Domains\Kasus\Services;

use App\Domains\Kasus\DataTransferObjects\KonselorKandidat;
use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\Siswa;
use Illuminate\Support\Collection;

class KonselorAllocationResolver
{
    public function kandidatUntuk(Siswa $siswa): Collection
    {
        $guruBk = Guru::withoutGlobalScopes()
            ->where('jenis_ptk', 'guru_bk')
            ->where('lembaga_id', $siswa->lembaga_id)
            ->where('status_aktif', 'aktif')
            ->get();

        if ($guruBk->isNotEmpty()) {
            return $guruBk->map(fn (Guru $guru) => new KonselorKandidat('guru', $guru));
        }

        $karyawanPool = Karyawan::withoutGlobalScopes()
            ->whereNull('lembaga_id')
            ->where('yayasan_id', $siswa->lembaga->yayasan_id)
            ->where('status_aktif', 'aktif')
            ->whereHas('jenisKaryawan', fn ($q) => $q->where('is_konselor', true))
            ->get();

        return $karyawanPool->map(fn (Karyawan $karyawan) => new KonselorKandidat('karyawan', $karyawan));
    }
}
```

`app/Domains/Kasus/Services/TugasBatchGenerator.php` — cuma ganti baris `namespace App\Services;` jadi `namespace App\Domains\Kasus\Services;`, SISA ISI (semua method `parseTanggalPengumpulanBulanan`, `tentukanFrekuensiAkhir`, `generate`, `generateHarian`, `generateMingguan`, `generateBulanan`, `hitungJatuhTempoBulanan`) SAMA PERSIS TANPA PERUBAHAN SATU BARIS PUN — copy isi asli dari `git show HEAD:app/Services/TugasBatchGenerator.php` setelah Task 1-2 selesai, cuma edit baris namespace.

- [ ] **Step 2: Update `use` di 3 consumer**

`app/Http/Controllers/Admin/KasusController.php`: ganti `use App\Services\KonselorAllocationResolver;` jadi `use App\Domains\Kasus\Services\KonselorAllocationResolver;`
`app/Http/Controllers/KasusTugasController.php`: ganti `use App\Services\TugasBatchGenerator;` jadi `use App\Domains\Kasus\Services\TugasBatchGenerator;`
`app/Http/Controllers/KasusTugasBatchPreviewController.php`: sama seperti di atas

Cari juga test file yang mengimpor kedua service ini: `grep -rln "App\\\\Services\\\\KonselorAllocationResolver\|App\\\\Services\\\\TugasBatchGenerator" tests` dan update.

- [ ] **Step 3: Jalankan test scoped**

Run: `php artisan test tests/Feature/Admin/KasusTriaseTest.php tests/Feature/KasusTugasBeriTest.php tests/Feature/KasusTugasBatchViewTest.php tests/Feature/KasusTugasBatchSchemaTest.php`
Expected: semua PASS.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "refactor(kasus): pindah KonselorAllocationResolver & TugasBatchGenerator ke Domains\Kasus\Services"
```

---

## Task 4: Buat `KasusPolicy` — Konsolidasi Otorisasi

**Files:**
- Create: `app/Domains/Kasus/Policies/KasusPolicy.php`
- Modify: `app/Providers/AppServiceProvider.php` (fallback registrasi kalau auto-discovery gagal)
- Test: `tests/Unit/Domains/Kasus/Policies/KasusPolicyTest.php`

**Interfaces:**
- Consumes: `App\Domains\Kasus\Models\Kasus`, `App\Domains\Kasus\Models\KasusTugasSubmission` (Task 1)
- Produces: `KasusPolicy::view(User, Kasus): bool`, `::downloadLampiran(User, Kasus, KasusTugasSubmission): bool`, `::kelolaSesiTugas(User, Kasus): bool` — dipakai Task 6 (Pengajuan, method `show`), Task 8 (Sesi), Task 9 (Tugas), Task 10 (Submission)

Logika method Policy DITRANSKRIP PERSIS dari kombinasi kondisi yang sudah ada di kode asli (`KasusController::show()` baris 162-173, `KasusTugasSubmissionController::download()` baris 105-115, dan `assertKonselorPemegangKasus` yang muncul di trait `AssertsKonselorPemegangKasus` + `KasusSesiController` + `KasusTugasSubmissionController` + inline `KasusEvaluasiController::store()`) — BUKAN dirancang ulang.

- [ ] **Step 1: Buat `KasusPolicy`**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Kasus\Policies;

use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusTugasSubmission;
use App\Models\User;

final class KasusPolicy
{
    /**
     * Dipindah persis dari trait AssertsKonselorPemegangKasus + duplikatnya di
     * KasusSesiController, KasusTugasSubmissionController, dan inline di
     * KasusEvaluasiController::store() — 4 salinan identik disatukan di sini.
     */
    public function isKonselor(User $user, Kasus $kasus): bool
    {
        $karyawanId = $user->karyawan()->withoutGlobalScope(\App\Models\Scopes\TenantScope::class)->first()?->id;

        return ($kasus->konselor_guru_id !== null && $kasus->konselor_guru_id === $user->guru?->id)
            || ($kasus->konselor_karyawan_id !== null && $kasus->konselor_karyawan_id === $karyawanId);
    }

    /**
     * Dipindah persis dari KasusController::show() baris 162-173 (versi sebelum migrasi).
     * $siswa WAJIB objek yang sudah di-resolve TANPA TenantScope oleh pemanggil
     * (sama seperti kode asli — Kasus->siswa lazy-load akan gagal untuk orang tua
     * tanpa lembaga_id kalau tidak sudah di-cache lewat setRelation()).
     */
    public function view(User $user, Kasus $kasus, \App\Models\Siswa $siswa): bool
    {
        $isSubmitter = ($kasus->diajukan_oleh_guru_id !== null && $kasus->diajukan_oleh_guru_id === $user->guru?->id)
            || ($kasus->diajukan_oleh_orang_tua_id !== null && $kasus->diajukan_oleh_orang_tua_id === $user->orangTua?->id);
        $isKontakUtama = $user->orangTua !== null
            && $siswa->orangTua()->where('orang_tua_id', $user->orangTua->id)->wherePivot('is_kontak_utama', true)->exists();
        $isTriaseAdmin = $user->can('kasus.triase')
            && ($user->widestScopeLevel() === 'yayasan' || $kasus->lembaga_id === $user->lembaga_id);
        $isSiswaTerkait = $user->siswa !== null && $user->siswa->id === $kasus->siswa_id;

        return $isSubmitter || $isKontakUtama || $isTriaseAdmin || $this->isKonselor($user, $kasus) || $isSiswaTerkait;
    }

    /**
     * Dipindah persis dari KasusTugasSubmissionController::download() baris 105-115.
     * $siswa WAJIB objek yang sudah di-resolve TANPA TenantScope oleh pemanggil.
     */
    public function downloadLampiran(User $user, Kasus $kasus, KasusTugasSubmission $submission, \App\Models\Siswa $siswa): bool
    {
        $isSubmitter = ($submission->siswa_id !== null && $submission->siswa_id === $user->siswa?->id)
            || ($submission->orang_tua_id !== null && $submission->orang_tua_id === $user->orangTua?->id);
        $isKontakUtama = $user->orangTua !== null
            && $siswa->orangTua()->where('orang_tua_id', $user->orangTua->id)->wherePivot('is_kontak_utama', true)->exists();
        $isTriaseAdmin = $user->can('kasus.triase')
            && ($user->widestScopeLevel() === 'yayasan' || $kasus->lembaga_id === $user->lembaga_id);

        return $isSubmitter || $this->isKonselor($user, $kasus) || $isKontakUtama || $isTriaseAdmin;
    }

    /**
     * Alias isKonselor untuk konteks Sesi/Tugas/Submission-review — mengganti trait
     * AssertsKonselorPemegangKasus dan 2 method privat duplikatnya. Nama method beda
     * dari isKonselor supaya panggilan $this->authorize('kelolaSesiTugas', $kasus)
     * di controller jelas menyatakan INTENT (bukan sekadar cek identitas).
     */
    public function kelolaSesiTugas(User $user, Kasus $kasus): bool
    {
        return $this->isKonselor($user, $kasus);
    }
}
```

- [ ] **Step 2: Tulis test `KasusPolicyTest` — cakup semua kombinasi role yang tadinya diperiksa inline**

```php
<?php

use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Policies\KasusPolicy;
use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('isKonselor returns true for the assigned konselor guru, false for another guru', function () {
    $lembaga = Lembaga::factory()->create();
    $guruKonselor = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $guruLain = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $userKonselor = User::factory()->create(['lembaga_id' => $lembaga->id])->guru()->save($guruKonselor) && $guruKonselor->fresh();
    $kasus = Kasus::factory()->create(['lembaga_id' => $lembaga->id, 'konselor_guru_id' => $guruKonselor->id]);

    $userA = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $userA->guru()->save($guruKonselor);
    $userB = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $userB->guru()->save($guruLain);

    $policy = new KasusPolicy;

    expect($policy->isKonselor($userA->fresh(), $kasus))->toBeTrue();
    expect($policy->isKonselor($userB->fresh(), $kasus))->toBeFalse();
});

it('view grants access to the guru submitter even when not the konselor', function () {
    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $guruPengaju = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->guru()->save($guruPengaju);
    $kasus = Kasus::factory()->create([
        'lembaga_id' => $lembaga->id,
        'siswa_id' => $siswa->id,
        'diajukan_oleh_guru_id' => $guruPengaju->id,
    ]);

    expect((new KasusPolicy)->view($user->fresh(), $kasus, $siswa))->toBeTrue();
});

it('view grants access to orang tua kontak utama but not a non-kontak-utama orang tua', function () {
    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kontakUtama = OrangTua::factory()->create();
    $bukanKontakUtama = OrangTua::factory()->create();
    $siswa->orangTua()->attach($kontakUtama->id, ['is_kontak_utama' => true]);
    $siswa->orangTua()->attach($bukanKontakUtama->id, ['is_kontak_utama' => false]);
    $userKontakUtama = User::factory()->create();
    $userKontakUtama->orangTua()->save($kontakUtama);
    $userBukanKontakUtama = User::factory()->create();
    $userBukanKontakUtama->orangTua()->save($bukanKontakUtama);
    $kasus = Kasus::factory()->create(['lembaga_id' => $lembaga->id, 'siswa_id' => $siswa->id]);

    $policy = new KasusPolicy;

    expect($policy->view($userKontakUtama->fresh(), $kasus, $siswa))->toBeTrue();
    expect($policy->view($userBukanKontakUtama->fresh(), $kasus, $siswa))->toBeFalse();
});

it('view grants access to a triase admin within the same lembaga but not a different lembaga', function () {
    Role::firstOrCreate(['name' => 'admin_lembaga', 'guard_name' => 'web', 'scope_level' => 'lembaga']);
    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'kasus.triase', 'guard_name' => 'web']);

    $lembagaA = Lembaga::factory()->create();
    $lembagaB = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembagaA->id]);
    $kasus = Kasus::factory()->create(['lembaga_id' => $lembagaA->id, 'siswa_id' => $siswa->id]);

    $adminA = User::factory()->create(['lembaga_id' => $lembagaA->id]);
    $adminA->givePermissionTo('kasus.triase');
    $adminB = User::factory()->create(['lembaga_id' => $lembagaB->id]);
    $adminB->givePermissionTo('kasus.triase');

    $policy = new KasusPolicy;

    expect($policy->view($adminA->fresh(), $kasus, $siswa))->toBeTrue();
    expect($policy->view($adminB->fresh(), $kasus, $siswa))->toBeFalse();
});

it('view grants access to the terkait siswa themselves', function () {
    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->siswa()->save($siswa);
    $kasus = Kasus::factory()->create(['lembaga_id' => $lembaga->id, 'siswa_id' => $siswa->id]);

    expect((new KasusPolicy)->view($user->fresh(), $kasus, $siswa))->toBeTrue();
});

it('view denies access to a user with no relation to the kasus at all', function () {
    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kasus = Kasus::factory()->create(['lembaga_id' => $lembaga->id, 'siswa_id' => $siswa->id]);
    $userAsing = User::factory()->create(['lembaga_id' => $lembaga->id]);

    expect((new KasusPolicy)->view($userAsing, $kasus, $siswa))->toBeFalse();
});

it('kelolaSesiTugas mirrors isKonselor exactly', function () {
    $lembaga = Lembaga::factory()->create();
    $karyawanKonselor = Karyawan::factory()->create(['lembaga_id' => $lembaga->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->karyawan()->save($karyawanKonselor);
    $kasus = Kasus::factory()->create(['lembaga_id' => $lembaga->id, 'konselor_karyawan_id' => $karyawanKonselor->id]);

    expect((new KasusPolicy)->kelolaSesiTugas($user->fresh(), $kasus))->toBeTrue();
});
```

- [ ] **Step 3: Jalankan test, verifikasi PASS**

Run: `php artisan test tests/Unit/Domains/Kasus/Policies/KasusPolicyTest.php`
Expected: 7 passed. Kalau ada factory yang butuh field wajib tambahan (mis. `Kasus::factory()` butuh `kategori_masalah`/`deskripsi`/`status` default), cek `database/factories/KasusFactory.php` untuk default yang sudah ada — jangan tambahkan field baru ke factory kalau tidak perlu.

- [ ] **Step 4: Verifikasi auto-discovery Policy, tambahkan fallback registrasi kalau perlu**

Tulis test kecil untuk verifikasi (boleh dihapus setelah dicek manual, atau simpan sebagai bagian test di atas):
```php
it('resolves KasusPolicy via auto-discovery or explicit registration', function () {
    $kasus = \App\Domains\Kasus\Models\Kasus::factory()->create();
    expect(\Illuminate\Support\Facades\Gate::getPolicyFor($kasus))->toBeInstanceOf(\App\Domains\Kasus\Policies\KasusPolicy::class);
});
```
Kalau test ini GAGAL (Policy tidak ketemu), tambahkan registrasi eksplisit di `app/Providers/AppServiceProvider.php`, method `boot()` (di awal method, sebelum `Auth::provider(...)`):
```php
\Illuminate\Support\Facades\Gate::policy(\App\Domains\Kasus\Models\Kasus::class, \App\Domains\Kasus\Policies\KasusPolicy::class);
```
Tambahkan `use Illuminate\Support\Facades\Gate;` ke bagian atas file kalau belum ada. Jalankan ulang test sampai PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Domains/Kasus/Policies/ app/Providers/AppServiceProvider.php tests/Unit/Domains/Kasus/Policies/
git commit -m "feat(kasus): buat KasusPolicy - konsolidasi 4 duplikasi otorisasi jadi 1 sumber"
```

---

## Task 5: Sub-Area Manajemen — Actions & Refactor 3 Controller Admin

**Files:**
- Create: `app/Domains/Kasus/DataTransferObjects/{TriaseKasusData,AssignKonselorData}.php`
- Create: `app/Domains/Kasus/Actions/Manajemen/{AssignKonselorAction,DestroyKasusAction,RestoreKasusAction}.php`
- Test: `tests/Unit/Domains/Kasus/Actions/Manajemen/AssignKonselorActionTest.php`
- Modify: `app/Http/Controllers/Admin/KasusController.php`, `app/Http/Controllers/Admin/KasusAksesLogController.php`, `app/Http/Controllers/Admin/KasusTerhapusController.php`

**Interfaces:**
- Consumes: `App\Domains\Kasus\Models\{Kasus,KasusConsent,KasusTugas}` (Task 1), `App\Domains\Kasus\Services\KonselorAllocationResolver` (Task 3)
- Produces: 3 Action, dipakai controller ini saja

`Admin\KasusAksesLogController` dan `Admin\KasusTerhapusController` (baris 75 dan 55, query kompleks tapi READ-ONLY, tidak ada mutasi) TETAP inline di controller (bukan diekstrak jadi Action) — mengikuti SKILL.md §23 "jangan tambah layer tanpa alasan": query read-only kompleks lebih jelas dibaca langsung di controller daripada dibungkus Action tanpa use-case mutasi yang jelas. `index()` method KEDUANYA dipindah APA ADANYA (tidak ada baris yang berubah selain tidak ada — controller ini tidak menyentuh model yang namespace-nya berubah kecuali lewat FQCN inline `\App\Models\Kasus::class` di `KasusAksesLogController` yang HARUS diupdate jadi `\App\Domains\Kasus\Models\Kasus::class`).

- [ ] **Step 1: Buat DTO**

`app/Domains/Kasus/DataTransferObjects/AssignKonselorData.php`:
```php
<?php

declare(strict_types=1);

namespace App\Domains\Kasus\DataTransferObjects;

final readonly class AssignKonselorData
{
    public function __construct(
        public string $tingkatUrgensi,
        public string $konselorTipe,
        public int $konselorId,
    ) {}
}
```

- [ ] **Step 2: Buat `AssignKonselorAction`**

Logika dipindah persis dari `Admin\KasusController::assignKonselor()` baris 54-93 versi sebelum migrasi (VALIDASI kandidat & permission tetap di controller — Action cuma eksekusi setelah validasi lolos, sesuai SKILL.md §5 "Domain tidak boleh terima Request langsung"):

```php
<?php

declare(strict_types=1);

namespace App\Domains\Kasus\Actions\Manajemen;

use App\Domains\Kasus\DataTransferObjects\AssignKonselorData;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusConsent;
use App\Enums\StatusKasus;
use App\Notifications\KonselorDipilihNotification;
use App\Models\Siswa;
use Illuminate\Support\Facades\DB;

final class AssignKonselorAction
{
    public function execute(Kasus $kasus, Siswa $siswa, AssignKonselorData $data): Kasus
    {
        DB::transaction(function () use ($data, $kasus) {
            $kasus->update([
                'tingkat_urgensi' => $data->tingkatUrgensi,
                'status' => StatusKasus::MenungguConsent,
                'konselor_guru_id' => $data->konselorTipe === 'guru' ? $data->konselorId : null,
                'konselor_karyawan_id' => $data->konselorTipe === 'karyawan' ? $data->konselorId : null,
            ]);

            KasusConsent::create(['kasus_id' => $kasus->id, 'jenis' => 'sesi_pendampingan']);
            KasusConsent::create(['kasus_id' => $kasus->id, 'jenis' => 'pengumpulan_media']);
        });

        $kontakUtama = $siswa->orangTua()->wherePivot('is_kontak_utama', true)->first();
        $kontakUtama?->notify(new KonselorDipilihNotification($kasus));

        return $kasus->fresh();
    }
}
```

- [ ] **Step 3: Buat `DestroyKasusAction` dan `RestoreKasusAction`**

Logika dipindah persis dari `destroy()` (baris 96-115) dan `restore()` (baris 117-135) versi sebelum migrasi.

`app/Domains/Kasus/Actions/Manajemen/DestroyKasusAction.php`:
```php
<?php

declare(strict_types=1);

namespace App\Domains\Kasus\Actions\Manajemen;

use App\Domains\Kasus\Models\Kasus;
use Illuminate\Support\Facades\DB;

final class DestroyKasusAction
{
    public function execute(Kasus $kasus): void
    {
        DB::transaction(function () use ($kasus) {
            foreach ($kasus->tugas as $tugas) {
                $tugas->submissions()->delete();
            }
            $kasus->sesi()->delete();
            $kasus->tugas()->delete();
            $kasus->evaluasi()->delete();
            $kasus->consents()->delete();
            $kasus->delete();
        });
    }
}
```

`app/Domains/Kasus/Actions/Manajemen/RestoreKasusAction.php`:
```php
<?php

declare(strict_types=1);

namespace App\Domains\Kasus\Actions\Manajemen;

use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusTugas;
use Illuminate\Support\Facades\DB;

final class RestoreKasusAction
{
    public function execute(Kasus $kasus): void
    {
        DB::transaction(function () use ($kasus) {
            $kasus->restore();
            $kasus->sesi()->withTrashed()->restore();
            KasusTugas::withTrashed()->where('kasus_id', $kasus->id)->get()->each(function (KasusTugas $tugas) {
                $tugas->submissions()->withTrashed()->restore();
                $tugas->restore();
            });
            $kasus->evaluasi()->withTrashed()->restore();
            $kasus->consents()->withTrashed()->restore();
        });
    }
}
```

- [ ] **Step 4: Tulis test untuk `AssignKonselorAction`**

```php
<?php

use App\Domains\Kasus\Actions\Manajemen\AssignKonselorAction;
use App\Domains\Kasus\DataTransferObjects\AssignKonselorData;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusConsent;
use App\Enums\StatusKasus;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('assigns a guru konselor, creates 2 consent rows, and sets status to menunggu_consent', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $kasus = Kasus::factory()->create(['lembaga_id' => $lembaga->id, 'siswa_id' => $siswa->id, 'status' => StatusKasus::Diajukan]);

    $result = (new AssignKonselorAction)->execute($kasus, $siswa, new AssignKonselorData(
        tingkatUrgensi: 'sedang',
        konselorTipe: 'guru',
        konselorId: $guru->id,
    ));

    expect($result->status)->toBe(StatusKasus::MenungguConsent)
        ->and($result->konselor_guru_id)->toBe($guru->id)
        ->and($result->konselor_karyawan_id)->toBeNull()
        ->and(KasusConsent::where('kasus_id', $kasus->id)->count())->toBe(2);
});
```

- [ ] **Step 5: Jalankan test, verifikasi PASS**

Run: `php artisan test tests/Unit/Domains/Kasus/Actions/Manajemen/AssignKonselorActionTest.php`
Expected: 1 passed.

- [ ] **Step 6: Refaktor `Admin\KasusController`**

Ganti seluruh isi jadi:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Kasus\Actions\Manajemen\AssignKonselorAction;
use App\Domains\Kasus\Actions\Manajemen\DestroyKasusAction;
use App\Domains\Kasus\Actions\Manajemen\RestoreKasusAction;
use App\Domains\Kasus\DataTransferObjects\AssignKonselorData;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Services\KonselorAllocationResolver;
use App\Enums\StatusKasus;
use App\Models\Scopes\TenantScope;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class KasusController extends BaseController
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('kasus.view');

        $kasusList = Kasus::with('siswa')->where('status', StatusKasus::Diajukan)->latest()->get();
        $totalSemua = Kasus::count();
        $totalProses = Kasus::whereIn('status', [StatusKasus::MenungguConsent, StatusKasus::Ditugaskan, StatusKasus::Berjalan, StatusKasus::Eskalasi])->count();

        return view('portals.lembaga.kasus.index', [
            'kasusList' => $kasusList,
            'totalMenunggu' => $kasusList->count(),
            'totalProses' => $totalProses,
            'totalSemua' => $totalSemua,
        ]);
    }

    public function triase(Kasus $kasus, KonselorAllocationResolver $resolver): View
    {
        $this->authorize('kasus.triase');
        $this->authorizeLembaga($kasus);
        abort_if($kasus->trashed(), 404);

        $siswa = $kasus->siswa()->withoutGlobalScope(TenantScope::class)->first();
        abort_if($siswa === null, 404);
        $kasus->setRelation('siswa', $siswa);

        $kandidat = $resolver->kandidatUntuk($siswa);

        return view('portals.lembaga.kasus.triase', ['kasus' => $kasus, 'kandidat' => $kandidat]);
    }

    public function assignKonselor(Request $request, Kasus $kasus, KonselorAllocationResolver $resolver, AssignKonselorAction $action): RedirectResponse
    {
        $this->authorize('kasus.triase');
        $this->authorizeLembaga($kasus);
        abort_if($kasus->trashed(), 404);

        $siswa = $kasus->siswa()->withoutGlobalScope(TenantScope::class)->first();
        abort_if($siswa === null, 404);
        $kasus->setRelation('siswa', $siswa);

        $data = $request->validate([
            'tingkat_urgensi' => ['required', 'in:rendah,sedang,tinggi'],
            'konselor_tipe' => ['required', 'in:guru,karyawan'],
            'konselor_id' => ['required', 'integer'],
        ]);

        $kandidat = $resolver->kandidatUntuk($siswa);
        $kandidatIds = $kandidat
            ->filter(fn ($k) => $k->tipe === $data['konselor_tipe'])
            ->map(fn ($k) => $k->model->id)
            ->all();

        abort_unless(in_array((int) $data['konselor_id'], $kandidatIds, true), 422, 'Konselor yang dipilih tidak valid.');

        $action->execute($kasus, $siswa, new AssignKonselorData(
            tingkatUrgensi: $data['tingkat_urgensi'],
            konselorTipe: $data['konselor_tipe'],
            konselorId: (int) $data['konselor_id'],
        ));

        return redirect()->route('admin.kasus.index')->with('status', 'Konselor berhasil ditugaskan, menunggu persetujuan orang tua.');
    }

    public function destroy(Kasus $kasus, DestroyKasusAction $action): RedirectResponse
    {
        $this->authorize('kasus.hapus');
        $this->authorizeLembaga($kasus);

        abort_unless($kasus->status === StatusKasus::Selesai, 422, 'Hanya kasus berstatus Selesai yang dapat dihapus.');

        $action->execute($kasus);

        return redirect()->route('admin.kasus.index')->with('status', 'Kasus berhasil dihapus.');
    }

    public function restore(Kasus $kasus, RestoreKasusAction $action): RedirectResponse
    {
        $this->authorize('kasus.pulihkan');
        $this->authorizeLembaga($kasus);
        abort_unless($kasus->trashed(), 404);

        $action->execute($kasus);

        return redirect()->route('admin.kasus.terhapus')->with('status', 'Kasus berhasil dipulihkan.');
    }

    private function authorizeLembaga(Kasus $kasus): void
    {
        $user = auth()->user();
        abort_if($user->widestScopeLevel() !== 'yayasan' && $kasus->lembaga_id !== $user->lembaga_id, 404);
    }
}
```

- [ ] **Step 7: Update `Admin\KasusAksesLogController` — cuma ganti FQCN model dan nama view**

Cari baris:
```php
                [\App\Models\Kasus::class],
```
(muncul 2 kali, baris ~28 dan ~41 versi sebelum migrasi) ganti jadi:
```php
                [\App\Domains\Kasus\Models\Kasus::class],
```
Cari baris `return view('admin.kasus.akses-log', [` ganti jadi `return view('portals.lembaga.kasus.akses-log', [`. SISA ISI FILE (query Activity, statistik, pencarian) TIDAK BERUBAH SATU BARIS PUN.

- [ ] **Step 8: Update `Admin\KasusTerhapusController` — cuma ganti import model dan nama view**

Ganti `use App\Models\Kasus;` jadi `use App\Domains\Kasus\Models\Kasus;`. Ganti `return view('admin.kasus.terhapus', [` jadi `return view('portals.lembaga.kasus.terhapus', [`. SISA ISI TIDAK BERUBAH.

- [ ] **Step 9: Jalankan test scoped Manajemen**

Run: `php artisan test tests/Feature/Admin/KasusTriaseTest.php tests/Feature/Admin/KasusSoftDeleteRestoreTest.php tests/Feature/Admin/KasusAksesLogViewTest.php tests/Feature/Admin/KasusTerhapusViewTest.php tests/Feature/KasusAksesKlinisLogTest.php tests/Unit/Domains/Kasus/Actions/Manajemen/AssignKonselorActionTest.php`
Expected: semua PASS. **CATATAN:** view belum dipindah fisik sampai Task 12 — kalau test gagal karena "View not found" untuk `portals.lembaga.kasus.*`, itu WAJAR di titik ini (view lama masih ada di `admin.kasus.*`, view baru belum ada filenya) — LANJUTKAN commit Task 5 apa adanya, perbaikan view menyeluruh terjadi di Task 12. Kalau mau memverifikasi controller Task 5 murni (tanpa terganggu isu view), jalankan test dengan sementara membiarkan `view()` call TETAP nama lama sampai Task 12 — **PILIH SALAH SATU pendekatan berikut dan JALANKAN SECARA KONSISTEN untuk Task 5-11**: (a) biarkan `view()` tetap nama LAMA di Task 5-11, baru diubah SEKALIGUS ke nama baru di Task 12 bersamaan pemindahan file view — RECOMMENDED, lebih aman karena tidak ada window waktu di mana kode\view saling tidak sinkron dalam commit yang sama.

**Revisi Step 6 & 7 & 8 di atas mengikuti pendekatan (a):** JANGAN ubah nama `view()` di Task 5 — tetap `admin.kasus.index`, `admin.kasus.triase`, `admin.kasus.akses-log`, `admin.kasus.terhapus`. Baris `view()` di Step 6 kode `Admin\KasusController` di atas, HAPUS perubahan `portals.lembaga.kasus.*`, kembalikan ke `admin.kasus.index` dan `admin.kasus.triase`. Step 7-8 juga TIDAK mengubah nama view, HANYA model FQCN. Nama view diubah SEKALIGUS untuk seluruh domain di Task 12.

- [ ] **Step 10: Jalankan ulang test scoped Manajemen setelah revisi Step 9**

Run: sama seperti Step 9. Expected: semua PASS (karena view() masih menunjuk nama lama yang filenya masih ada).

- [ ] **Step 11: Commit**

```bash
git add app/Domains/Kasus/DataTransferObjects/AssignKonselorData.php app/Domains/Kasus/Actions/Manajemen/ app/Http/Controllers/Admin/KasusController.php app/Http/Controllers/Admin/KasusAksesLogController.php app/Http/Controllers/Admin/KasusTerhapusController.php tests/Unit/Domains/Kasus/Actions/Manajemen/
git commit -m "refactor(kasus): ekstrak Action sub-area Manajemen, controller admin jadi thin"
```

---

## Task 6: Sub-Area Pengajuan — Action & Refactor `KasusController` (top-level)

**Files:**
- Create: `app/Domains/Kasus/DataTransferObjects/AjukanKasusData.php`
- Create: `app/Domains/Kasus/Actions/Pengajuan/{ListKasusUntukUserAction,AjukanKasusAction}.php`
- Test: `tests/Unit/Domains/Kasus/Actions/Pengajuan/AjukanKasusActionTest.php`
- Modify: `app/Http/Controllers/KasusController.php`

**Interfaces:**
- Consumes: `App\Domains\Kasus\Models\Kasus` (Task 1), `App\Domains\Kasus\Policies\KasusPolicy` (Task 4, dipakai method `show()`)
- Produces: 2 Action, dipakai controller ini saja

**PENTING:** `index()` (baris 23-79 versi asli) berisi percabangan 5 role BERBEDA (siswa/orang_tua/karyawan/guru/default) dengan query yang beda-beda signifikan — DIEKSTRAK APA ADANYA jadi 1 Action (`ListKasusUntukUserAction`), JANGAN dipecah jadi 5 Action terpisah (itu bukan 5 use-case bisnis berbeda, itu 1 use-case "lihat daftar kasus milik saya" dengan variasi query berdasar peran — memecahnya jadi 5 Action justru melanggar YAGNI/DRY, § 23 SKILL.md).

- [ ] **Step 1: Buat DTO**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Kasus\DataTransferObjects;

final readonly class AjukanKasusData
{
    public function __construct(
        public int $siswaId,
        public string $kategoriMasalah,
        public string $deskripsi,
        public ?string $lampiranPath,
    ) {}
}
```

- [ ] **Step 2: Buat `ListKasusUntukUserAction`**

Logika dipindah PERSIS dari `KasusController::index()` baris 23-79 versi sebelum migrasi (SEMUA komentar keamanan tentang bypass TenantScope untuk orang tua DIPERTAHANKAN):

```php
<?php

declare(strict_types=1);

namespace App\Domains\Kasus\Actions\Pengajuan;

use App\Domains\Kasus\Models\Kasus;
use App\Enums\StatusKasus;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use Illuminate\Support\Collection;

final class ListKasusUntukUserAction
{
    public function execute(User $user): Collection
    {
        if ($user->hasRole('siswa')) {
            $kasusList = Kasus::with('siswa')->where('siswa_id', $user->siswa?->id)->latest()->get();
        } elseif ($user->hasRole('orang_tua')) {
            // Orang tua accounts have no lembaga_id of their own, so the default TenantScope
            // on Kasus (a real, non-null lembaga_id) would fail-closed to zero rows for them.
            // Bypass it here. Show every kasus this orang_tua either submitted themselves OR
            // is the kontak utama for (matching the exact access rule show() uses) — filtering
            // only by diajukan_oleh_orang_tua_id missed every kasus a GURU submitted for their
            // child, at any status, since that column stays null in that case.
            $orangTuaId = $user->orangTua?->id;
            $kasusList = Kasus::withoutGlobalScope(TenantScope::class)
                ->with(['siswa' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)])
                ->where(fn ($q) => $q
                    ->where('diajukan_oleh_orang_tua_id', $orangTuaId)
                    ->orWhereHas('siswa', fn ($q2) => $q2->withoutGlobalScope(TenantScope::class)
                        ->whereHas('orangTua', fn ($q3) => $q3->where('orang_tua.id', $orangTuaId)
                            ->where('siswa_orang_tua.is_kontak_utama', true))))
                ->latest()->get();
        } elseif ($user->hasRole('karyawan_pool') || $user->hasRole('karyawan_lembaga')) {
            $karyawanId = $user->karyawan()->withoutGlobalScope(TenantScope::class)->first()?->id;
            $kasusList = Kasus::withoutGlobalScope(TenantScope::class)
                ->with(['siswa' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)])
                ->where('konselor_karyawan_id', $karyawanId)->latest()->get();
        } elseif ($user->hasRole('guru')) {
            $kasusList = Kasus::with('siswa')
                ->where(fn ($q) => $q->where('diajukan_oleh_guru_id', $user->guru?->id)
                    ->orWhere('konselor_guru_id', $user->guru?->id))
                ->latest()->get();
        } else {
            $kasusList = Kasus::with('siswa')->latest()->get();
        }

        return $kasusList->sortByDesc(fn ($k) => $k->status === StatusKasus::Eskalasi ? 1 : 0)->values();
    }
}
```

- [ ] **Step 3: Buat `AjukanKasusAction`**

Logika dipindah PERSIS dari `store()` baris 94-148 dan `notifyPihakLain()` baris 198-210 versi sebelum migrasi:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Kasus\Actions\Pengajuan;

use App\Domains\Kasus\DataTransferObjects\AjukanKasusData;
use App\Domains\Kasus\Models\Kasus;
use App\Enums\StatusKasus;
use App\Models\Scopes\TenantScope;
use App\Models\Siswa;
use App\Notifications\KasusDiajukanNotification;
use Illuminate\Support\Facades\DB;

final class AjukanKasusAction
{
    public function execute(Siswa $siswa, AjukanKasusData $data, bool $isGuru, int $guruId = null, int $orangTuaId = null): Kasus
    {
        $kasus = DB::transaction(function () use ($data, $siswa, $isGuru, $guruId, $orangTuaId) {
            return Kasus::create([
                'siswa_id' => $siswa->id,
                'lembaga_id' => $siswa->lembaga_id,
                'diajukan_oleh_guru_id' => $isGuru ? $guruId : null,
                'diajukan_oleh_orang_tua_id' => $isGuru ? null : $orangTuaId,
                'kategori_masalah' => $data->kategoriMasalah,
                'deskripsi' => $data->deskripsi,
                'lampiran' => $data->lampiranPath,
                'status' => StatusKasus::Diajukan,
            ]);
        });

        // The Kasus->siswa relation would re-apply Siswa's TenantScope when lazy-loaded,
        // which (for an orang_tua submitter with no lembaga_id) filters the real siswa row
        // out entirely. Cache the already-authorized, scope-bypassed $siswa on the relation
        // so notifyPihakLain() (and the redirect target) see the correct record.
        $kasus->setRelation('siswa', $siswa);

        $this->notifyPihakLain($kasus, $isGuru);

        return $kasus;
    }

    private function notifyPihakLain(Kasus $kasus, bool $isGuru): void
    {
        if ($isGuru) {
            $kontakUtama = $kasus->siswa->orangTua()->wherePivot('is_kontak_utama', true)->first();
            $kontakUtama?->notify(new KasusDiajukanNotification($kasus));

            return;
        }

        $kelas = $kasus->siswa->kelas()->withoutGlobalScope(TenantScope::class)->first();
        $waliKelas = $kelas?->waliKelas()->withoutGlobalScope(TenantScope::class)->first();
        $waliKelas?->notify(new KasusDiajukanNotification($kasus));
    }
}
```

- [ ] **Step 4: Tulis test untuk `AjukanKasusAction`**

```php
<?php

use App\Domains\Kasus\Actions\Pengajuan\AjukanKasusAction;
use App\Domains\Kasus\DataTransferObjects\AjukanKasusData;
use App\Domains\Kasus\Models\Kasus;
use App\Enums\StatusKasus;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('creates a kasus submitted by guru with diajukan_oleh_guru_id set and status diajukan', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    $kasus = (new AjukanKasusAction)->execute(
        $siswa,
        new AjukanKasusData(siswaId: $siswa->id, kategoriMasalah: 'Akademik', deskripsi: 'Nilai turun drastis', lampiranPath: null),
        isGuru: true,
        guruId: $guru->id,
    );

    expect($kasus->status)->toBe(StatusKasus::Diajukan)
        ->and($kasus->diajukan_oleh_guru_id)->toBe($guru->id)
        ->and($kasus->diajukan_oleh_orang_tua_id)->toBeNull()
        ->and(Kasus::where('siswa_id', $siswa->id)->count())->toBe(1);
});
```

- [ ] **Step 5: Jalankan test, verifikasi PASS**

Run: `php artisan test tests/Unit/Domains/Kasus/Actions/Pengajuan/AjukanKasusActionTest.php`
Expected: 1 passed.

- [ ] **Step 6: Refaktor `KasusController` (top-level)**

Ganti seluruh isi jadi (nama VIEW TETAP `kasus.index`/`.create`/`.show` untuk sekarang, diubah Task 12; method `show()` memakai `KasusPolicy` menggantikan kondisi inline):

```php
<?php

namespace App\Http\Controllers;

use App\Domains\Kasus\Actions\Pengajuan\AjukanKasusAction;
use App\Domains\Kasus\Actions\Pengajuan\ListKasusUntukUserAction;
use App\Domains\Kasus\DataTransferObjects\AjukanKasusData;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Policies\KasusPolicy;
use App\Models\Scopes\TenantScope;
use App\Models\Siswa;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class KasusController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request, ListKasusUntukUserAction $action): View
    {
        $this->authorize('kasus.view');

        $kasusList = $action->execute($request->user());

        $totalKasus = $kasusList->count();
        $totalBerjalan = $kasusList->where('status', \App\Enums\StatusKasus::Berjalan)->count();
        $totalEskalasi = $kasusList->where('status', \App\Enums\StatusKasus::Eskalasi)->count();
        $totalButuhTindakan = $kasusList->filter(fn ($k) => in_array($k->status, [
            \App\Enums\StatusKasus::Diajukan,
            \App\Enums\StatusKasus::MenungguConsent,
            \App\Enums\StatusKasus::Eskalasi,
        ], true))->count();

        return view('kasus.index', [
            'kasusList' => $kasusList,
            'totalKasus' => $totalKasus,
            'totalBerjalan' => $totalBerjalan,
            'totalEskalasi' => $totalEskalasi,
            'totalButuhTindakan' => $totalButuhTindakan,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('kasus.ajukan');

        $user = $request->user();

        $siswaList = $user->hasRole('orang_tua')
            ? ($user->orangTua?->siswa()->withoutGlobalScope(TenantScope::class)->orderBy('nama_lengkap')->get() ?? collect())
            : Siswa::orderBy('nama_lengkap')->get();

        return view('kasus.create', ['siswaList' => $siswaList]);
    }

    public function store(Request $request, AjukanKasusAction $action): RedirectResponse
    {
        $this->authorize('kasus.ajukan');

        $user = $request->user();
        $isGuru = $user->hasRole('guru');

        $rules = [
            'siswa_id' => ['required', 'exists:siswa,id'],
            'kategori_masalah' => ['required', 'string', 'max:255'],
            'deskripsi' => ['required', 'string'],
        ];
        if ($isGuru) {
            $rules['lampiran'] = ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'];
        }
        $data = $request->validate($rules);

        if ($isGuru) {
            abort_if($user->guru === null, 403);
            $siswa = Siswa::findOrFail($data['siswa_id']);
        } else {
            abort_if($user->orangTua === null, 403);
            $siswa = $user->orangTua->siswa()
                ->withoutGlobalScope(TenantScope::class)
                ->where('siswa.id', $data['siswa_id'])
                ->firstOrFail();
        }

        $lampiranPath = ($isGuru && $request->hasFile('lampiran'))
            ? $request->file('lampiran')->store('kasus-lampiran', 'public')
            : null;

        $action->execute(
            $siswa,
            new AjukanKasusData(
                siswaId: $siswa->id,
                kategoriMasalah: $data['kategori_masalah'],
                deskripsi: $data['deskripsi'],
                lampiranPath: $lampiranPath,
            ),
            isGuru: $isGuru,
            guruId: $isGuru ? $user->guru->id : null,
            orangTuaId: $isGuru ? null : $user->orangTua->id,
        );

        return redirect()->route('kasus.index')->with('status', 'Kasus berhasil diajukan.');
    }

    public function show(Kasus $kasus, KasusPolicy $policy): View
    {
        $this->authorize('kasus.view');

        $user = auth()->user();

        abort_if($kasus->trashed(), 404);

        $siswa = $kasus->siswa()->withoutGlobalScope(TenantScope::class)->first();
        abort_if($siswa === null, 404);
        $kasus->setRelation('siswa', $siswa);

        abort_if(! $policy->view($user, $kasus, $siswa), 404);

        activity('akses_klinis')
            ->causedBy($user)
            ->performedOn($kasus)
            ->log('Membuka detail kasus');

        // Guru and Karyawan both use BelongsToTenant. For an orang_tua actor (null lembaga_id),
        // TenantScope would fail-closed to zero rows for these konselor relations, silently
        // hiding the assigned konselor's identity from the informed-consent screen.
        $kasus->load([
            'consents',
            'konselorGuru' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
            'konselorKaryawan' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
        ]);

        $karyawanId = $user->karyawan()->withoutGlobalScope(TenantScope::class)->first()?->id;

        return view('kasus.show', [
            'kasus' => $kasus,
            'isKontakUtama' => $user->orangTua !== null
                && $siswa->orangTua()->where('orang_tua_id', $user->orangTua->id)->wherePivot('is_kontak_utama', true)->exists(),
            'isKonselor' => ($kasus->konselor_guru_id !== null && $kasus->konselor_guru_id === $user->guru?->id)
                || ($kasus->konselor_karyawan_id !== null && $kasus->konselor_karyawan_id === $karyawanId),
            'isSiswaTerkait' => $user->siswa !== null && $user->siswa->id === $kasus->siswa_id,
            'isTriaseAdmin' => $user->can('kasus.triase')
                && ($user->widestScopeLevel() === 'yayasan' || $kasus->lembaga_id === $user->lembaga_id),
        ]);
    }
}
```

**Catatan penting:** variabel `isKontakUtama`/`isKonselor`/`isSiswaTerkait`/`isTriaseAdmin` yang dikirim ke VIEW (bukan buat keputusan otorisasi — keputusan sudah lewat `$policy->view()`) TETAP dihitung inline persis seperti kode asli karena view (`kasus.show`, dipindah Task 12) memakainya untuk menampilkan tombol/tab yang berbeda per role. INI BUKAN duplikasi logic otorisasi — `$policy->view()` menjawab "boleh akses halaman ini sama sekali?", sementara 4 variabel ini menjawab "role APA orangnya, untuk kontrol tampilan". Jangan hapus salah satunya.

- [ ] **Step 7: Jalankan test scoped Pengajuan**

Run: `php artisan test tests/Feature/KasusListingTest.php tests/Feature/KasusSchemaTest.php tests/Feature/KasusShowDeleteButtonTest.php tests/Feature/KasusKonselorAksesTest.php tests/Feature/KasusAutoBerjalanTest.php tests/Feature/DashboardKasusTest.php tests/Feature/DashboardKasusAdminTest.php tests/Unit/Domains/Kasus/Actions/Pengajuan/AjukanKasusActionTest.php`
Expected: semua PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Domains/Kasus/DataTransferObjects/AjukanKasusData.php app/Domains/Kasus/Actions/Pengajuan/ app/Http/Controllers/KasusController.php tests/Unit/Domains/Kasus/Actions/Pengajuan/
git commit -m "refactor(kasus): ekstrak Action sub-area Pengajuan, pakai KasusPolicy di show()"
```

---

## Task 7: Sub-Area Consent — Action & Refactor `KasusConsentController`

**Files:**
- Create: `app/Domains/Kasus/Actions/Consent/ApproveConsentAction.php`
- Test: `tests/Unit/Domains/Kasus/Actions/Consent/ApproveConsentActionTest.php`
- Modify: `app/Http/Controllers/KasusConsentController.php`

**Interfaces:**
- Consumes: `App\Domains\Kasus\Models\{Kasus,KasusConsent}` (Task 1)
- Produces: 1 Action, dipakai controller ini saja

- [ ] **Step 1: Buat `ApproveConsentAction`**

Logika dipindah PERSIS dari `approve()` baris 20-51 dan `notifyKasusDitugaskan()` baris 54-71 versi sebelum migrasi:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Kasus\Actions\Consent;

use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusConsent;
use App\Enums\StatusKasus;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Notifications\ConsentDisetujuiNotification;
use Illuminate\Support\Facades\DB;

final class ApproveConsentAction
{
    public function execute(Kasus $kasus, KasusConsent $kasusConsent): void
    {
        DB::transaction(function () use ($kasus, $kasusConsent) {
            $kasusConsent->update(['status' => 'disetujui', 'disetujui_at' => now()]);

            if ($kasusConsent->jenis === 'sesi_pendampingan') {
                $kasus->update(['status' => StatusKasus::Ditugaskan]);
            }
        });

        if ($kasusConsent->jenis === 'sesi_pendampingan') {
            $this->notifyKasusDitugaskan($kasus);
        }
    }

    private function notifyKasusDitugaskan(Kasus $kasus): void
    {
        $guruPengaju = $kasus->diajukanOlehGuru()->withoutGlobalScope(TenantScope::class)->first();
        $guruPengaju?->notify(new ConsentDisetujuiNotification($kasus));

        // Avoid Spatie's ->role() query scope here: it throws RoleDoesNotExist when the
        // 'admin_akademik' role hasn't been created yet in the current guard (e.g. in tests
        // that don't need lembaga-admin notifications). whereHas() degrades to zero matches
        // instead, which is the correct behavior for a best-effort notification fan-out.
        $lembagaAdmins = User::withoutGlobalScope(TenantScope::class)
            ->whereHas('roles', fn ($query) => $query->where('name', 'admin_akademik'))
            ->where('lembaga_id', $kasus->lembaga_id)
            ->get();

        foreach ($lembagaAdmins as $admin) {
            $admin->notify(new ConsentDisetujuiNotification($kasus));
        }
    }
}
```

- [ ] **Step 2: Tulis test**

```php
<?php

use App\Domains\Kasus\Actions\Consent\ApproveConsentAction;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusConsent;
use App\Enums\StatusKasus;
use App\Models\Lembaga;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('approving the sesi_pendampingan consent moves kasus status to ditugaskan', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kasus = Kasus::factory()->create(['lembaga_id' => $lembaga->id, 'siswa_id' => $siswa->id, 'status' => StatusKasus::MenungguConsent]);
    $consent = KasusConsent::factory()->create(['kasus_id' => $kasus->id, 'jenis' => 'sesi_pendampingan', 'status' => 'menunggu']);

    (new ApproveConsentAction)->execute($kasus, $consent);

    expect($kasus->fresh()->status)->toBe(StatusKasus::Ditugaskan)
        ->and($consent->fresh()->status)->toBe('disetujui')
        ->and($consent->fresh()->disetujui_at)->not->toBeNull();
});

it('approving the pengumpulan_media consent does not change kasus status', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kasus = Kasus::factory()->create(['lembaga_id' => $lembaga->id, 'siswa_id' => $siswa->id, 'status' => StatusKasus::MenungguConsent]);
    $consent = KasusConsent::factory()->create(['kasus_id' => $kasus->id, 'jenis' => 'pengumpulan_media', 'status' => 'menunggu']);

    (new ApproveConsentAction)->execute($kasus, $consent);

    expect($kasus->fresh()->status)->toBe(StatusKasus::MenungguConsent)
        ->and($consent->fresh()->status)->toBe('disetujui');
});
```

- [ ] **Step 3: Jalankan test, verifikasi PASS**

Run: `php artisan test tests/Unit/Domains/Kasus/Actions/Consent/ApproveConsentActionTest.php`
Expected: 2 passed.

- [ ] **Step 4: Refaktor `KasusConsentController`**

```php
<?php

namespace App\Http\Controllers;

use App\Domains\Kasus\Actions\Consent\ApproveConsentAction;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusConsent;
use App\Models\Scopes\TenantScope;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller as BaseController;

class KasusConsentController extends BaseController
{
    use AuthorizesRequests;

    public function approve(Kasus $kasus, KasusConsent $kasusConsent, ApproveConsentAction $action): RedirectResponse
    {
        $this->authorize('kasus.consent');

        $orangTua = auth()->user()->orangTua;
        abort_if($orangTua === null, 403);

        $siswa = $kasus->siswa()->withoutGlobalScope(TenantScope::class)->first();
        abort_if($siswa === null, 404);
        $kasus->setRelation('siswa', $siswa);

        $isKontakUtama = $siswa->orangTua()
            ->where('orang_tua_id', $orangTua->id)
            ->wherePivot('is_kontak_utama', true)
            ->exists();

        abort_if(! $isKontakUtama, 403, 'Anda bukan kontak utama untuk siswa ini.');
        abort_if($kasusConsent->kasus_id !== $kasus->id, 404);

        $action->execute($kasus, $kasusConsent);

        return redirect()->route('kasus.show', $kasus)->with('status', 'Persetujuan berhasil disimpan.');
    }
}
```

- [ ] **Step 5: Jalankan test scoped**

Run: `php artisan test tests/Feature/KasusConsentTest.php tests/Unit/Domains/Kasus/Actions/Consent/ApproveConsentActionTest.php`
Expected: semua PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Kasus/Actions/Consent/ app/Http/Controllers/KasusConsentController.php tests/Unit/Domains/Kasus/Actions/Consent/
git commit -m "refactor(kasus): ekstrak Action sub-area Consent"
```

---

## Task 8: Sub-Area Sesi — Action & Refactor `KasusSesiController`

**Files:**
- Create: `app/Domains/Kasus/DataTransferObjects/{JadwalkanSesiData,UpdateStatusSesiData}.php`
- Create: `app/Domains/Kasus/Actions/Sesi/{JadwalkanSesiAction,UpdateStatusSesiAction}.php`
- Test: `tests/Unit/Domains/Kasus/Actions/Sesi/JadwalkanSesiActionTest.php`
- Modify: `app/Http/Controllers/KasusSesiController.php`

**Interfaces:**
- Consumes: `App\Domains\Kasus\Models\{Kasus,KasusSesi}` (Task 1), `App\Domains\Kasus\Policies\KasusPolicy::kelolaSesiTugas()` (Task 4)
- Produces: 2 Action, dipakai controller ini saja

- [ ] **Step 1: Buat DTO**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Kasus\DataTransferObjects;

final readonly class JadwalkanSesiData
{
    /**
     * @param  array<int, array{dijadwalkan_pada: string, peserta: string, lokasi_mode: string}>  $sesi
     */
    public function __construct(
        public array $sesi,
    ) {}
}
```

```php
<?php

declare(strict_types=1);

namespace App\Domains\Kasus\DataTransferObjects;

final readonly class UpdateStatusSesiData
{
    public function __construct(
        public string $status,
        public ?string $catatanInternal,
        public ?string $alasanBatal,
    ) {}
}
```

- [ ] **Step 2: Buat `JadwalkanSesiAction`**

Logika dipindah PERSIS dari `store()` baris 21-63 versi sebelum migrasi:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Kasus\Actions\Sesi;

use App\Domains\Kasus\DataTransferObjects\JadwalkanSesiData;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusSesi;
use App\Models\Scopes\TenantScope;
use App\Notifications\SesiDijadwalkanNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class JadwalkanSesiAction
{
    public function execute(Kasus $kasus, JadwalkanSesiData $data): Collection
    {
        $created = DB::transaction(function () use ($data, $kasus) {
            $rows = collect($data->sesi)->map(fn ($row) => KasusSesi::create([
                'kasus_id' => $kasus->id,
                'dijadwalkan_pada' => $row['dijadwalkan_pada'],
                'peserta' => $row['peserta'],
                'lokasi_mode' => $row['lokasi_mode'],
            ]));

            if ($kasus->status->value === 'ditugaskan') {
                $kasus->update(['status' => 'berjalan']);
            }

            return $rows;
        });

        $siswa = $kasus->siswa()->withoutGlobalScope(TenantScope::class)->first();

        foreach ($created as $sesi) {
            if (in_array($sesi->peserta, ['siswa', 'keduanya'], true)) {
                $siswa?->user()->withoutGlobalScope(TenantScope::class)->first()?->notify(new SesiDijadwalkanNotification($sesi));
            }
            if (in_array($sesi->peserta, ['orang_tua', 'keduanya'], true)) {
                $kontakUtama = $siswa?->orangTua()->wherePivot('is_kontak_utama', true)->first();
                $kontakUtama?->notify(new SesiDijadwalkanNotification($sesi));
            }
        }

        return $created;
    }
}
```

- [ ] **Step 3: Buat `UpdateStatusSesiAction`**

Logika dipindah PERSIS dari `updateStatus()` baris 65-85 versi sebelum migrasi:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Kasus\Actions\Sesi;

use App\Domains\Kasus\DataTransferObjects\UpdateStatusSesiData;
use App\Domains\Kasus\Models\KasusSesi;

final class UpdateStatusSesiAction
{
    public function execute(KasusSesi $kasusSesi, UpdateStatusSesiData $data): KasusSesi
    {
        $kasusSesi->update([
            'status' => $data->status,
            'catatan_internal' => $data->catatanInternal ?? $kasusSesi->catatan_internal,
            'alasan_batal' => $data->alasanBatal ?? null,
        ]);

        return $kasusSesi->fresh();
    }
}
```

- [ ] **Step 4: Tulis test untuk `JadwalkanSesiAction`**

```php
<?php

use App\Domains\Kasus\Actions\Sesi\JadwalkanSesiAction;
use App\Domains\Kasus\DataTransferObjects\JadwalkanSesiData;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusSesi;
use App\Enums\StatusKasus;
use App\Models\Lembaga;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('creates sesi rows and moves kasus from ditugaskan to berjalan', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kasus = Kasus::factory()->create(['lembaga_id' => $lembaga->id, 'siswa_id' => $siswa->id, 'status' => StatusKasus::Ditugaskan]);

    $created = (new JadwalkanSesiAction)->execute($kasus, new JadwalkanSesiData(sesi: [
        ['dijadwalkan_pada' => now()->addDay()->toDateTimeString(), 'peserta' => 'siswa', 'lokasi_mode' => 'daring'],
        ['dijadwalkan_pada' => now()->addDays(2)->toDateTimeString(), 'peserta' => 'orang_tua', 'lokasi_mode' => 'tatap_muka'],
    ]));

    expect($created)->toHaveCount(2)
        ->and($kasus->fresh()->status)->toBe(StatusKasus::Berjalan)
        ->and(KasusSesi::where('kasus_id', $kasus->id)->count())->toBe(2);
});

it('does not change kasus status if it is already berjalan', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kasus = Kasus::factory()->create(['lembaga_id' => $lembaga->id, 'siswa_id' => $siswa->id, 'status' => StatusKasus::Berjalan]);

    (new JadwalkanSesiAction)->execute($kasus, new JadwalkanSesiData(sesi: [
        ['dijadwalkan_pada' => now()->addDay()->toDateTimeString(), 'peserta' => 'siswa', 'lokasi_mode' => 'daring'],
    ]));

    expect($kasus->fresh()->status)->toBe(StatusKasus::Berjalan);
});
```

- [ ] **Step 5: Jalankan test, verifikasi PASS**

Run: `php artisan test tests/Unit/Domains/Kasus/Actions/Sesi/JadwalkanSesiActionTest.php`
Expected: 2 passed.

- [ ] **Step 6: Refaktor `KasusSesiController`**

```php
<?php

namespace App\Http\Controllers;

use App\Domains\Kasus\Actions\Sesi\JadwalkanSesiAction;
use App\Domains\Kasus\Actions\Sesi\UpdateStatusSesiAction;
use App\Domains\Kasus\DataTransferObjects\JadwalkanSesiData;
use App\Domains\Kasus\DataTransferObjects\UpdateStatusSesiData;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusSesi;
use App\Enums\StatusKasus;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class KasusSesiController extends BaseController
{
    use AuthorizesRequests;

    public function store(Request $request, Kasus $kasus, JadwalkanSesiAction $action): RedirectResponse
    {
        $this->authorize('kasus.view');
        $this->authorize('kelolaSesiTugas', $kasus);
        abort_if($kasus->trashed(), 404);
        abort_unless(in_array($kasus->status, [StatusKasus::Ditugaskan, StatusKasus::Berjalan, StatusKasus::Eskalasi], true), 403);

        $data = $request->validate([
            'sesi' => ['required', 'array', 'min:1'],
            'sesi.*.dijadwalkan_pada' => ['required', 'date'],
            'sesi.*.peserta' => ['required', 'in:siswa,orang_tua,keduanya'],
            'sesi.*.lokasi_mode' => ['required', 'string', 'max:255'],
        ]);

        $action->execute($kasus, new JadwalkanSesiData(sesi: $data['sesi']));

        return redirect()->route('kasus.show', $kasus)->with('status', 'Sesi berhasil dijadwalkan.');
    }

    public function updateStatus(Request $request, Kasus $kasus, KasusSesi $kasusSesi, UpdateStatusSesiAction $action): RedirectResponse
    {
        $this->authorize('kasus.view');
        $this->authorize('kelolaSesiTugas', $kasus);
        abort_if($kasusSesi->kasus_id !== $kasus->id, 404);
        abort_if($kasusSesi->status->value !== 'terjadwal', 403);

        $data = $request->validate([
            'status' => ['required', 'in:selesai,batal,tidak_hadir'],
            'catatan_internal' => ['nullable', 'string'],
            'alasan_batal' => ['required_if:status,batal', 'nullable', 'string'],
        ]);

        $action->execute($kasusSesi, new UpdateStatusSesiData(
            status: $data['status'],
            catatanInternal: $data['catatan_internal'] ?? null,
            alasanBatal: $data['alasan_batal'] ?? null,
        ));

        return redirect()->route('kasus.show', $kasus)->with('status', 'Status sesi berhasil diperbarui.');
    }
}
```

**Catatan:** `$this->authorize('kelolaSesiTugas', $kasus)` memanggil `KasusPolicy::kelolaSesiTugas()` (Task 4) — menggantikan trait `AssertsKonselorPemegangKasus` DAN method privat `assertKonselorPemegangKasus()` yang tadinya ada di file ini. `abort_unless($isKonselor, 403)` di kode asli setara dengan `$this->authorize()` yang otomatis melempar 403 kalau Policy return false — perilaku identik.

- [ ] **Step 7: Jalankan test scoped**

Run: `php artisan test tests/Feature/KasusSesiJadwalTest.php tests/Feature/KasusSesiStatusTest.php tests/Feature/Console/KirimReminderSesiTest.php tests/Unit/Domains/Kasus/Actions/Sesi/JadwalkanSesiActionTest.php`
Expected: semua PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Domains/Kasus/DataTransferObjects/JadwalkanSesiData.php app/Domains/Kasus/DataTransferObjects/UpdateStatusSesiData.php app/Domains/Kasus/Actions/Sesi/ app/Http/Controllers/KasusSesiController.php tests/Unit/Domains/Kasus/Actions/Sesi/
git commit -m "refactor(kasus): ekstrak Action sub-area Sesi, pakai KasusPolicy::kelolaSesiTugas"
```

---

## Task 9: Sub-Area Tugas — Action & Refactor 2 Controller

**Files:**
- Create: `app/Domains/Kasus/DataTransferObjects/BeriTugasBatchData.php`
- Create: `app/Domains/Kasus/Actions/Tugas/{BeriTugasBatchAction,TandaiTugasSelesaiAction}.php`
- Test: `tests/Unit/Domains/Kasus/Actions/Tugas/BeriTugasBatchActionTest.php`
- Modify: `app/Http/Controllers/KasusTugasController.php`, `app/Http/Controllers/KasusTugasBatchPreviewController.php`

**Interfaces:**
- Consumes: `App\Domains\Kasus\Models\{Kasus,KasusTugas}` (Task 1), `App\Domains\Kasus\Services\TugasBatchGenerator` (Task 3), `App\Domains\Kasus\Policies\KasusPolicy::kelolaSesiTugas()` (Task 4)
- Produces: 2 Action, dipakai controller ini saja. `KasusTugasBatchPreviewController` TIDAK dapat Action baru — logikanya (panggil `TugasBatchGenerator` langsung, format JSON) TETAP inline di controller karena murni transformasi read-only tanpa mutasi (sama alasan seperti `KasusAksesLogController` di Task 5).

- [ ] **Step 1: Buat DTO**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Kasus\DataTransferObjects;

final readonly class BeriTugasBatchData
{
    public function __construct(
        public string $judul,
        public string $instruksi,
        public string $frekuensi,
        public string $tanggalMulai,
        public string $tanggalSelesai,
        public mixed $tanggalPengumpulanBulananRaw,
    ) {}
}
```

- [ ] **Step 2: Buat `BeriTugasBatchAction`**

Logika dipindah PERSIS dari `store()` baris 27-90 versi sebelum migrasi:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Kasus\Actions\Tugas;

use App\Domains\Kasus\DataTransferObjects\BeriTugasBatchData;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusTugas;
use App\Domains\Kasus\Services\TugasBatchGenerator;
use App\Models\Scopes\TenantScope;
use App\Notifications\TugasBatchDibuatNotification;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class BeriTugasBatchAction
{
    public function __construct(
        private readonly TugasBatchGenerator $generator
    ) {}

    public function execute(Kasus $kasus, BeriTugasBatchData $data): Collection
    {
        [$tanggalPengumpulanBulanan, $akhirBulan] = $this->generator->parseTanggalPengumpulanBulanan($data->tanggalPengumpulanBulananRaw);

        $tanggalMulai = Carbon::parse($data->tanggalMulai);
        $tanggalSelesai = Carbon::parse($data->tanggalSelesai);

        // Frekuensi yang benar-benar dipakai bisa berbeda dari yang dipilih konselor (fallback
        // bulanan->mingguan atau mingguan->harian jika rentangnya terlalu pendek). Baris yang
        // dibuat harus mencatat frekuensi INI, sama seperti yang sudah ditampilkan di pratinjau,
        // bukan nilai form mentah — lihat KasusTugasBatchPreviewController::preview().
        $frekuensiAkhir = $this->generator->tentukanFrekuensiAkhir($data->frekuensi, $tanggalMulai, $tanggalSelesai);

        $barisTanggal = $this->generator->generate(
            $data->frekuensi,
            $tanggalMulai,
            $tanggalSelesai,
            $tanggalPengumpulanBulanan,
            $akhirBulan,
        );

        $created = DB::transaction(function () use ($data, $kasus, $barisTanggal, $frekuensiAkhir) {
            $batchId = (string) Str::uuid();
            $batchTotal = $barisTanggal->count();

            $rows = $barisTanggal->values()->map(fn ($baris, $index) => KasusTugas::create([
                'kasus_id' => $kasus->id,
                'judul' => $data->judul,
                'instruksi' => $data->instruksi,
                'frekuensi' => $frekuensiAkhir,
                'batch_id' => $batchId,
                'batch_urutan' => $index + 1,
                'batch_total' => $batchTotal,
                'mulai_pada' => $baris['mulai_pada'],
                'batas_selesai_pada' => $baris['batas_selesai_pada'],
            ]));

            if ($kasus->status->value === 'ditugaskan') {
                $kasus->update(['status' => 'berjalan']);
            }

            return $rows;
        });

        $siswa = $kasus->siswa()->withoutGlobalScope(TenantScope::class)->first();
        $kontakUtama = $siswa?->orangTua()->wherePivot('is_kontak_utama', true)->first();
        $siswaUser = $siswa?->user()->withoutGlobalScope(TenantScope::class)->first();

        // Satu notifikasi ringkasan per penerima untuk SELURUH batch, bukan satu notifikasi
        // per baris — sebuah batch harian/bulanan yang panjang bisa menghasilkan puluhan
        // baris kasus_tugas dalam satu submit, dan mengirim notifikasi terpisah untuk
        // masing-masing akan membanjiri siswa/orang tua (keputusan desain 2026-08-06).
        $siswaUser?->notify(new TugasBatchDibuatNotification($created));
        $kontakUtama?->notify(new TugasBatchDibuatNotification($created));

        return $created;
    }
}
```

- [ ] **Step 3: Buat `TandaiTugasSelesaiAction`**

Logika dipindah PERSIS dari `markSelesai()` baris 92-105 versi sebelum migrasi:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Kasus\Actions\Tugas;

use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusTugas;
use App\Models\Scopes\TenantScope;
use App\Notifications\TugasSelesaiNotification;

final class TandaiTugasSelesaiAction
{
    public function execute(Kasus $kasus, KasusTugas $kasusTugas): void
    {
        $kasusTugas->update(['status' => 'selesai']);

        $siswa = $kasus->siswa()->withoutGlobalScope(TenantScope::class)->first();
        $kontakUtama = $siswa?->orangTua()->wherePivot('is_kontak_utama', true)->first();
        $kontakUtama?->notify(new TugasSelesaiNotification($kasusTugas));
    }
}
```

- [ ] **Step 4: Tulis test untuk `BeriTugasBatchAction`**

```php
<?php

use App\Domains\Kasus\Actions\Tugas\BeriTugasBatchAction;
use App\Domains\Kasus\DataTransferObjects\BeriTugasBatchData;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusTugas;
use App\Domains\Kasus\Services\TugasBatchGenerator;
use App\Enums\StatusKasus;
use App\Models\Lembaga;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('generates one kasus_tugas row per day for a harian batch and moves kasus to berjalan', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kasus = Kasus::factory()->create(['lembaga_id' => $lembaga->id, 'siswa_id' => $siswa->id, 'status' => StatusKasus::Ditugaskan]);

    $created = (new BeriTugasBatchAction(new TugasBatchGenerator))->execute($kasus, new BeriTugasBatchData(
        judul: 'Jurnal Harian',
        instruksi: 'Tulis 1 paragraf refleksi tiap hari',
        frekuensi: 'harian',
        tanggalMulai: now()->toDateString(),
        tanggalSelesai: now()->addDays(2)->toDateString(),
        tanggalPengumpulanBulananRaw: null,
    ));

    expect($created)->toHaveCount(3)
        ->and($kasus->fresh()->status)->toBe(StatusKasus::Berjalan)
        ->and(KasusTugas::where('kasus_id', $kasus->id)->where('batch_id', $created->first()->batch_id)->count())->toBe(3);
});
```

- [ ] **Step 5: Jalankan test, verifikasi PASS**

Run: `php artisan test tests/Unit/Domains/Kasus/Actions/Tugas/BeriTugasBatchActionTest.php`
Expected: 1 passed.

- [ ] **Step 6: Refaktor `KasusTugasController`**

```php
<?php

namespace App\Http\Controllers;

use App\Domains\Kasus\Actions\Tugas\BeriTugasBatchAction;
use App\Domains\Kasus\Actions\Tugas\TandaiTugasSelesaiAction;
use App\Domains\Kasus\DataTransferObjects\BeriTugasBatchData;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusTugas;
use App\Enums\StatusKasus;
use App\Http\Requests\StoreKasusTugasBatchRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller as BaseController;

class KasusTugasController extends BaseController
{
    use AuthorizesRequests;

    public function store(StoreKasusTugasBatchRequest $request, Kasus $kasus, BeriTugasBatchAction $action): RedirectResponse
    {
        $this->authorize('kasus.view');
        $this->authorize('kelolaSesiTugas', $kasus);
        abort_if($kasus->trashed(), 404);
        abort_unless(in_array($kasus->status, [StatusKasus::Ditugaskan, StatusKasus::Berjalan, StatusKasus::Eskalasi], true), 403);

        $data = $request->validated();

        $created = $action->execute($kasus, new BeriTugasBatchData(
            judul: $data['judul'],
            instruksi: $data['instruksi'],
            frekuensi: $data['frekuensi'],
            tanggalMulai: $data['tanggal_mulai'],
            tanggalSelesai: $data['tanggal_selesai'],
            tanggalPengumpulanBulananRaw: $data['tanggal_pengumpulan_bulanan'] ?? null,
        ));

        return redirect()->route('kasus.show', $kasus)->with('status', "Tugas berhasil diberikan ({$created->count()} baris dibuat).");
    }

    public function markSelesai(Kasus $kasus, KasusTugas $kasusTugas, TandaiTugasSelesaiAction $action): RedirectResponse
    {
        $this->authorize('kasus.view');
        abort_if($kasusTugas->kasus_id !== $kasus->id, 404);
        $this->authorize('kelolaSesiTugas', $kasus);

        $action->execute($kasus, $kasusTugas);

        return redirect()->route('kasus.show', $kasus)->with('status', 'Tugas ditandai selesai.');
    }
}
```

- [ ] **Step 7: Refaktor `KasusTugasBatchPreviewController` — ganti import & Policy, logic TIDAK berubah**

```php
<?php

namespace App\Http\Controllers;

use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Services\TugasBatchGenerator;
use App\Enums\StatusKasus;
use App\Http\Requests\PreviewKasusTugasBatchRequest;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;

class KasusTugasBatchPreviewController extends BaseController
{
    use AuthorizesRequests;

    public function preview(PreviewKasusTugasBatchRequest $request, Kasus $kasus, TugasBatchGenerator $generator): JsonResponse
    {
        $this->authorize('kasus.view');
        $this->authorize('kelolaSesiTugas', $kasus);
        abort_if($kasus->trashed(), 404);
        abort_unless(in_array($kasus->status, [StatusKasus::Ditugaskan, StatusKasus::Berjalan, StatusKasus::Eskalasi], true), 403);

        $data = $request->validated();

        [$tanggalPengumpulanBulanan, $akhirBulan] = $generator->parseTanggalPengumpulanBulanan($data['tanggal_pengumpulan_bulanan'] ?? null);

        $tanggalMulai = Carbon::parse($data['tanggal_mulai']);
        $tanggalSelesai = Carbon::parse($data['tanggal_selesai']);

        $frekuensiAkhir = $generator->tentukanFrekuensiAkhir($data['frekuensi'], $tanggalMulai, $tanggalSelesai);
        $barisTanggal = $generator->generate($data['frekuensi'], $tanggalMulai, $tanggalSelesai, $tanggalPengumpulanBulanan, $akhirBulan);

        return response()->json([
            'frekuensi_akhir' => $frekuensiAkhir,
            'jumlah_baris' => $barisTanggal->count(),
            'baris' => $barisTanggal->map(fn ($baris) => [
                'mulai_pada' => $baris['mulai_pada']->toDateString(),
                'batas_selesai_pada' => $baris['batas_selesai_pada']->toDateString(),
            ])->values(),
        ]);
    }
}
```

**Catatan:** trait `AssertsKonselorPemegangKasus` sudah TIDAK dipakai lagi setelah Task 8-9 (diganti `$this->authorize('kelolaSesiTugas', $kasus)` di semua titik pemakaiannya). Trait file `app/Http/Controllers/Concerns/AssertsKonselorPemegangKasus.php` TIDAK dihapus di task ini (mungkin masih dipakai controller lain yang belum di-scan) — cek dulu dengan `grep -rln "AssertsKonselorPemegangKasus" app/Http/Controllers` sebelum task ini dianggap selesai; kalau HANYA `KasusTugasController.php` dan `KasusTugasBatchPreviewController.php` (yang barusan dihapus pemakaiannya) yang muncul, hapus trait-nya di Step 8 sebagai bagian task ini.

- [ ] **Step 8: Hapus trait yang sudah tidak dipakai (kalau benar 0 pemakaian tersisa)**

```bash
grep -rln "AssertsKonselorPemegangKasus" app/Http/Controllers
```
Kalau hasilnya kosong (0 file, karena file traitnya sendiri tidak match pattern `use AssertsKonselorPemegangKasus` dalam isi controller — cek dengan hati-hati, definisi trait sendiri di `Concerns/AssertsKonselorPemegangKasus.php` PASTI match nama filenya sendiri kalau di-grep nama file, jadi fokus ke isi PEMAKAIAN `use App\Http\Controllers\Concerns\AssertsKonselorPemegangKasus;` DAN `use AssertsKonselorPemegangKasus;` di dalam class body):
```bash
git rm app/Http/Controllers/Concerns/AssertsKonselorPemegangKasus.php
```
Kalau MASIH ada pemakaian di file lain yang belum disentuh plan ini, JANGAN hapus — laporkan sebagai temuan di commit message, biarkan trait tetap ada untuk sekarang.

- [ ] **Step 9: Jalankan test scoped**

Run: `php artisan test tests/Feature/KasusTugasBeriTest.php tests/Feature/KasusTugasBatchViewTest.php tests/Feature/KasusTugasBatchSchemaTest.php tests/Feature/Console/TandaiTugasTerlewatTest.php tests/Unit/Domains/Kasus/Actions/Tugas/BeriTugasBatchActionTest.php`
Expected: semua PASS.

- [ ] **Step 10: Commit**

```bash
git add -A
git commit -m "refactor(kasus): ekstrak Action sub-area Tugas, pakai KasusPolicy::kelolaSesiTugas, hapus trait duplikat"
```

---

## Task 10: Sub-Area Submission — Action & Refactor `KasusTugasSubmissionController`

**Files:**
- Create: `app/Domains/Kasus/DataTransferObjects/{SubmitBuktiTugasData,ReviewSubmissionData}.php`
- Create: `app/Domains/Kasus/Actions/Submission/{SubmitBuktiTugasAction,ReviewSubmissionAction}.php`
- Test: `tests/Unit/Domains/Kasus/Actions/Submission/ReviewSubmissionActionTest.php`
- Modify: `app/Http/Controllers/KasusTugasSubmissionController.php`

**Interfaces:**
- Consumes: `App\Domains\Kasus\Models\{Kasus,KasusConsent,KasusTugas,KasusTugasSubmission}` (Task 1), `App\Domains\Kasus\Policies\KasusPolicy::{kelolaSesiTugas,downloadLampiran}` (Task 4)
- Produces: 2 Action, dipakai controller ini saja. `download()` TIDAK dapat Action baru (read-only, langsung `Storage::disk('local')->download()`).

- [ ] **Step 1: Buat DTO**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Kasus\DataTransferObjects;

final readonly class SubmitBuktiTugasData
{
    public function __construct(
        public ?string $teks,
        public ?string $lampiranPath,
    ) {}
}
```

```php
<?php

declare(strict_types=1);

namespace App\Domains\Kasus\DataTransferObjects;

final readonly class ReviewSubmissionData
{
    public function __construct(
        public string $statusReview,
        public ?string $catatanRevisi,
    ) {}
}
```

- [ ] **Step 2: Buat `SubmitBuktiTugasAction`**

Logika dipindah PERSIS dari `store()` baris 23-62 versi sebelum migrasi (validasi & storage file TETAP di controller — Action cuma create record, sesuai SKILL.md §5):

```php
<?php

declare(strict_types=1);

namespace App\Domains\Kasus\Actions\Submission;

use App\Domains\Kasus\DataTransferObjects\SubmitBuktiTugasData;
use App\Domains\Kasus\Models\KasusTugas;
use App\Domains\Kasus\Models\KasusTugasSubmission;

final class SubmitBuktiTugasAction
{
    public function execute(KasusTugas $kasusTugas, SubmitBuktiTugasData $data, bool $isSiswaTerkait, int $siswaId, ?int $orangTuaId): KasusTugasSubmission
    {
        return KasusTugasSubmission::create([
            'tugas_id' => $kasusTugas->id,
            'siswa_id' => $isSiswaTerkait ? $siswaId : null,
            'orang_tua_id' => $isSiswaTerkait ? null : $orangTuaId,
            'teks' => $data->teks,
            'lampiran' => $data->lampiranPath,
        ]);
    }
}
```

- [ ] **Step 3: Buat `ReviewSubmissionAction`**

Logika dipindah PERSIS dari `review()` baris 64-92 versi sebelum migrasi:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Kasus\Actions\Submission;

use App\Domains\Kasus\DataTransferObjects\ReviewSubmissionData;
use App\Domains\Kasus\Models\KasusTugas;
use App\Domains\Kasus\Models\KasusTugasSubmission;
use App\Models\Scopes\TenantScope;
use App\Notifications\SubmissionRevisiNotification;

final class ReviewSubmissionAction
{
    public function execute(KasusTugas $kasusTugas, KasusTugasSubmission $kasusTugasSubmission, ReviewSubmissionData $data): KasusTugasSubmission
    {
        $kasusTugasSubmission->update([
            'status_review' => $data->statusReview,
            'catatan_revisi' => $data->catatanRevisi,
        ]);

        if ($data->statusReview === 'revisi_diminta') {
            $kasusTugas->update(['status' => 'revisi']);

            $notifiable = $kasusTugasSubmission->siswa_id !== null
                ? $kasusTugasSubmission->siswa()->withoutGlobalScope(TenantScope::class)->first()
                    ?->user()->withoutGlobalScope(TenantScope::class)->first()
                : $kasusTugasSubmission->orangTua;
            $notifiable?->notify(new SubmissionRevisiNotification($kasusTugasSubmission));
        }

        return $kasusTugasSubmission->fresh();
    }
}
```

- [ ] **Step 4: Tulis test untuk `ReviewSubmissionAction`**

```php
<?php

use App\Domains\Kasus\Actions\Submission\ReviewSubmissionAction;
use App\Domains\Kasus\DataTransferObjects\ReviewSubmissionData;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusTugas;
use App\Domains\Kasus\Models\KasusTugasSubmission;
use App\Models\Lembaga;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('requesting revisi_diminta moves the tugas status to revisi', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kasus = Kasus::factory()->create(['lembaga_id' => $lembaga->id, 'siswa_id' => $siswa->id]);
    $tugas = KasusTugas::factory()->create(['kasus_id' => $kasus->id, 'status' => 'dikerjakan']);
    $submission = KasusTugasSubmission::factory()->create(['tugas_id' => $tugas->id, 'siswa_id' => $siswa->id]);

    $result = (new ReviewSubmissionAction)->execute($tugas, $submission, new ReviewSubmissionData(
        statusReview: 'revisi_diminta',
        catatanRevisi: 'Foto kurang jelas, tolong ulangi.',
    ));

    expect($result->status_review)->toBe('revisi_diminta')
        ->and($tugas->fresh()->status->value)->toBe('revisi');
});

it('accepting a submission does not change the tugas status', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kasus = Kasus::factory()->create(['lembaga_id' => $lembaga->id, 'siswa_id' => $siswa->id]);
    $tugas = KasusTugas::factory()->create(['kasus_id' => $kasus->id, 'status' => 'dikerjakan']);
    $submission = KasusTugasSubmission::factory()->create(['tugas_id' => $tugas->id, 'siswa_id' => $siswa->id]);

    (new ReviewSubmissionAction)->execute($tugas, $submission, new ReviewSubmissionData(
        statusReview: 'diterima',
        catatanRevisi: null,
    ));

    expect($tugas->fresh()->status->value)->toBe('dikerjakan');
});
```

- [ ] **Step 5: Jalankan test, verifikasi PASS**

Run: `php artisan test tests/Unit/Domains/Kasus/Actions/Submission/ReviewSubmissionActionTest.php`
Expected: 2 passed.

- [ ] **Step 6: Refaktor `KasusTugasSubmissionController`**

```php
<?php

namespace App\Http\Controllers;

use App\Domains\Kasus\Actions\Submission\ReviewSubmissionAction;
use App\Domains\Kasus\Actions\Submission\SubmitBuktiTugasAction;
use App\Domains\Kasus\DataTransferObjects\ReviewSubmissionData;
use App\Domains\Kasus\DataTransferObjects\SubmitBuktiTugasData;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusConsent;
use App\Domains\Kasus\Models\KasusTugas;
use App\Domains\Kasus\Models\KasusTugasSubmission;
use App\Domains\Kasus\Policies\KasusPolicy;
use App\Models\Scopes\TenantScope;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KasusTugasSubmissionController extends BaseController
{
    use AuthorizesRequests;

    public function store(Request $request, Kasus $kasus, KasusTugas $kasusTugas, SubmitBuktiTugasAction $action): RedirectResponse
    {
        $this->authorize('kasus.view');
        abort_if($kasusTugas->kasus_id !== $kasus->id, 404);
        abort_if(! in_array($kasusTugas->status->value, ['ditugaskan', 'dikerjakan', 'revisi'], true), 403);

        $user = $request->user();
        $siswa = $kasus->siswa()->withoutGlobalScope(TenantScope::class)->first();
        abort_if($siswa === null, 404);

        $isSiswaTerkait = $user->siswa !== null && $user->siswa->id === $siswa->id;
        $isKontakUtama = $user->orangTua !== null
            && $siswa->orangTua()->where('orang_tua_id', $user->orangTua->id)->wherePivot('is_kontak_utama', true)->exists();

        abort_unless($isSiswaTerkait || $isKontakUtama, 403);

        $mediaDisetujui = KasusConsent::where('kasus_id', $kasus->id)
            ->where('jenis', 'pengumpulan_media')->where('status', 'disetujui')->exists();
        $hasLampiran = $mediaDisetujui && $request->hasFile('lampiran');

        $rules = ['teks' => [$hasLampiran ? 'nullable' : 'required', 'string']];
        if ($mediaDisetujui) {
            $rules['lampiran'] = ['nullable', 'file', 'mimes:jpg,jpeg,png,mp4,mov', 'max:20480'];
        }
        $data = $request->validate($rules);

        $lampiranPath = ($mediaDisetujui && $request->hasFile('lampiran'))
            ? $request->file('lampiran')->store('kasus-tugas-lampiran', 'local')
            : null;

        $action->execute(
            $kasusTugas,
            new SubmitBuktiTugasData(teks: $data['teks'] ?? null, lampiranPath: $lampiranPath),
            isSiswaTerkait: $isSiswaTerkait,
            siswaId: $siswa->id,
            orangTuaId: $isSiswaTerkait ? null : $user->orangTua->id,
        );

        return redirect()->route('kasus.show', $kasus)->with('status', 'Bukti pengerjaan berhasil dikirim.');
    }

    public function review(Request $request, Kasus $kasus, KasusTugas $kasusTugas, KasusTugasSubmission $kasusTugasSubmission, ReviewSubmissionAction $action): RedirectResponse
    {
        $this->authorize('kasus.view');
        abort_if($kasusTugas->kasus_id !== $kasus->id, 404);
        abort_if($kasusTugasSubmission->tugas_id !== $kasusTugas->id, 404);
        $this->authorize('kelolaSesiTugas', $kasus);

        $data = $request->validate([
            'status_review' => ['required', 'in:diterima,revisi_diminta'],
            'catatan_revisi' => ['required_if:status_review,revisi_diminta', 'nullable', 'string'],
        ]);

        $action->execute($kasusTugas, $kasusTugasSubmission, new ReviewSubmissionData(
            statusReview: $data['status_review'],
            catatanRevisi: $data['catatan_revisi'] ?? null,
        ));

        return redirect()->route('kasus.show', $kasus)->with('status', 'Review submission berhasil disimpan.');
    }

    public function download(Kasus $kasus, KasusTugas $kasusTugas, KasusTugasSubmission $kasusTugasSubmission, KasusPolicy $policy): StreamedResponse
    {
        $this->authorize('kasus.view');
        abort_if($kasusTugas->kasus_id !== $kasus->id, 404);
        abort_if($kasusTugasSubmission->tugas_id !== $kasusTugas->id, 404);
        abort_if($kasusTugasSubmission->lampiran === null, 404);

        $user = auth()->user();
        $siswa = $kasus->siswa()->withoutGlobalScope(TenantScope::class)->first();
        abort_if($siswa === null, 404);

        abort_if(! $policy->downloadLampiran($user, $kasus, $kasusTugasSubmission, $siswa), 404);

        return Storage::disk('local')->download($kasusTugasSubmission->lampiran);
    }
}
```

- [ ] **Step 7: Jalankan test scoped**

Run: `php artisan test tests/Feature/KasusTugasSubmissionTest.php tests/Feature/KasusTugasReviewTest.php tests/Feature/KasusSubmissionTest.php tests/Unit/Domains/Kasus/Actions/Submission/ReviewSubmissionActionTest.php`
Expected: semua PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Domains/Kasus/DataTransferObjects/SubmitBuktiTugasData.php app/Domains/Kasus/DataTransferObjects/ReviewSubmissionData.php app/Domains/Kasus/Actions/Submission/ app/Http/Controllers/KasusTugasSubmissionController.php tests/Unit/Domains/Kasus/Actions/Submission/
git commit -m "refactor(kasus): ekstrak Action sub-area Submission, pakai KasusPolicy::downloadLampiran"
```

---

## Task 11: Sub-Area Evaluasi — Action & Refactor `KasusEvaluasiController`

**Files:**
- Create: `app/Domains/Kasus/DataTransferObjects/CatatEvaluasiData.php`
- Create: `app/Domains/Kasus/Actions/Evaluasi/CatatEvaluasiAction.php`
- Test: `tests/Unit/Domains/Kasus/Actions/Evaluasi/CatatEvaluasiActionTest.php`
- Modify: `app/Http/Controllers/KasusEvaluasiController.php`

**Interfaces:**
- Consumes: `App\Domains\Kasus\Models\{Kasus,KasusEvaluasi}` (Task 1), `App\Domains\Kasus\Policies\KasusPolicy::isKonselor()` (Task 4)
- Produces: 1 Action, dipakai controller ini saja

**PENTING:** logika `store()` di kode asli punya percabangan otorisasi BERBEDA tergantung `$originalStatus` (`berjalan` → cek konselor; `eskalasi` → cek permission `kasus.triase` + lembaga) — ini TETAP di controller (bukan di Policy/Action) karena melibatkan `$this->authorize()` (permission generik) yang berbeda per cabang, bukan resource-check murni. Cuma pemanggilan `isKonselor` yang diganti pakai `KasusPolicy`.

- [ ] **Step 1: Buat DTO**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Kasus\DataTransferObjects;

final readonly class CatatEvaluasiData
{
    public function __construct(
        public string $catatan,
        public string $keputusan,
        public int $dibuatOlehUserId,
    ) {}
}
```

- [ ] **Step 2: Buat `CatatEvaluasiAction`**

Logika dipindah PERSIS dari bagian `DB::transaction` di `store()` (baris 51-70) DAN `notifyEvaluasi()` (baris 77-108) versi sebelum migrasi. Perhitungan `$newStatus` (baris 51-56, bergantung `$originalStatus` yang berasal dari validasi controller) tetap jadi parameter masuk, BUKAN dihitung ulang di Action:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Kasus\Actions\Evaluasi;

use App\Domains\Kasus\DataTransferObjects\CatatEvaluasiData;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusEvaluasi;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Notifications\KasusDikembalikanNotification;
use App\Notifications\KasusEskalasiNotification;
use App\Notifications\KasusSelesaiNotification;
use Illuminate\Support\Facades\DB;

final class CatatEvaluasiAction
{
    public function execute(Kasus $kasus, CatatEvaluasiData $data, string $newStatus, string $originalStatus): void
    {
        DB::transaction(function () use ($kasus, $data, $newStatus) {
            KasusEvaluasi::create([
                'kasus_id' => $kasus->id,
                'tanggal' => now(),
                'catatan' => $data->catatan,
                'keputusan' => $data->keputusan,
                'dibuat_oleh_user_id' => $data->dibuatOlehUserId,
            ]);

            if ($newStatus !== $kasus->status->value) {
                $kasus->update(['status' => $newStatus]);
            }
        });

        $this->notifyEvaluasi($kasus, $data->keputusan, $originalStatus);
    }

    private function notifyEvaluasi(Kasus $kasus, string $keputusan, string $originalStatus): void
    {
        if ($keputusan === 'eskalasi') {
            $admins = User::withoutGlobalScope(TenantScope::class)
                ->whereHas('roles', fn ($q) => $q->where('name', 'admin_akademik'))
                ->where('lembaga_id', $kasus->lembaga_id)
                ->get();
            foreach ($admins as $admin) {
                $admin->notify(new KasusEskalasiNotification($kasus));
            }

            return;
        }

        if ($keputusan === 'lanjut' && $originalStatus === 'eskalasi') {
            $konselorUser = $kasus->konselor_guru_id !== null
                ? $kasus->konselorGuru()->withoutGlobalScope(TenantScope::class)->first()?->user()->withoutGlobalScope(TenantScope::class)->first()
                : $kasus->konselorKaryawan()->withoutGlobalScope(TenantScope::class)->first()?->user()->withoutGlobalScope(TenantScope::class)->first();
            $konselorUser?->notify(new KasusDikembalikanNotification($kasus));

            return;
        }

        if ($keputusan === 'selesai') {
            $siswa = $kasus->siswa()->withoutGlobalScope(TenantScope::class)->first();
            $kontakUtama = $siswa?->orangTua()->wherePivot('is_kontak_utama', true)->first();
            $kontakUtama?->notify(new KasusSelesaiNotification($kasus));

            $guruPengaju = $kasus->diajukanOlehGuru()->withoutGlobalScope(TenantScope::class)->first();
            $guruPengaju?->user()->withoutGlobalScope(TenantScope::class)->first()?->notify(new KasusSelesaiNotification($kasus));
        }
    }
}
```

- [ ] **Step 3: Tulis test untuk `CatatEvaluasiAction`**

```php
<?php

use App\Domains\Kasus\Actions\Evaluasi\CatatEvaluasiAction;
use App\Domains\Kasus\DataTransferObjects\CatatEvaluasiData;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusEvaluasi;
use App\Enums\StatusKasus;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('records an evaluasi row and transitions kasus status to selesai', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kasus = Kasus::factory()->create(['lembaga_id' => $lembaga->id, 'siswa_id' => $siswa->id, 'status' => StatusKasus::Berjalan]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    (new CatatEvaluasiAction)->execute(
        $kasus,
        new CatatEvaluasiData(catatan: 'Siswa sudah stabil.', keputusan: 'selesai', dibuatOlehUserId: $user->id),
        newStatus: 'selesai',
        originalStatus: 'berjalan',
    );

    expect($kasus->fresh()->status)->toBe(StatusKasus::Selesai)
        ->and(KasusEvaluasi::where('kasus_id', $kasus->id)->count())->toBe(1);
});

it('does not update kasus status when newStatus equals current status', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kasus = Kasus::factory()->create(['lembaga_id' => $lembaga->id, 'siswa_id' => $siswa->id, 'status' => StatusKasus::Berjalan]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    (new CatatEvaluasiAction)->execute(
        $kasus,
        new CatatEvaluasiData(catatan: 'Masih perlu lanjut.', keputusan: 'lanjut', dibuatOlehUserId: $user->id),
        newStatus: 'berjalan',
        originalStatus: 'berjalan',
    );

    expect($kasus->fresh()->status)->toBe(StatusKasus::Berjalan);
});
```

- [ ] **Step 4: Jalankan test, verifikasi PASS**

Run: `php artisan test tests/Unit/Domains/Kasus/Actions/Evaluasi/CatatEvaluasiActionTest.php`
Expected: 2 passed.

- [ ] **Step 5: Refaktor `KasusEvaluasiController`**

```php
<?php

namespace App\Http\Controllers;

use App\Domains\Kasus\Actions\Evaluasi\CatatEvaluasiAction;
use App\Domains\Kasus\DataTransferObjects\CatatEvaluasiData;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Policies\KasusPolicy;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class KasusEvaluasiController extends BaseController
{
    use AuthorizesRequests;

    public function store(Request $request, Kasus $kasus, CatatEvaluasiAction $action, KasusPolicy $policy): RedirectResponse
    {
        $this->authorize('kasus.view');
        abort_if($kasus->trashed(), 404);
        $user = auth()->user();
        $originalStatus = $kasus->status->value;

        if ($originalStatus === 'berjalan') {
            abort_unless($policy->isKonselor($user, $kasus), 403);

            $data = $request->validate([
                'catatan' => ['required', 'string'],
                'keputusan' => ['required', 'in:lanjut,eskalasi,selesai'],
            ]);
        } elseif ($originalStatus === 'eskalasi') {
            $this->authorize('kasus.triase');
            abort_if($user->widestScopeLevel() !== 'yayasan' && $kasus->lembaga_id !== $user->lembaga_id, 404);

            $data = $request->validate([
                'catatan' => ['required', 'string'],
                'keputusan' => ['required', 'in:lanjut,selesai'],
            ]);
        } else {
            abort(404);
        }

        $newStatus = match (true) {
            $data['keputusan'] === 'eskalasi' => 'eskalasi',
            $data['keputusan'] === 'selesai' => 'selesai',
            $data['keputusan'] === 'lanjut' && $originalStatus === 'eskalasi' => 'berjalan',
            default => $originalStatus,
        };

        $action->execute(
            $kasus,
            new CatatEvaluasiData(catatan: $data['catatan'], keputusan: $data['keputusan'], dibuatOlehUserId: $user->id),
            newStatus: $newStatus,
            originalStatus: $originalStatus,
        );

        return redirect()->route('kasus.show', $kasus)->with('status', 'Evaluasi berhasil disimpan.');
    }
}
```

- [ ] **Step 6: Jalankan test scoped**

Run: `php artisan test tests/Feature/KasusEvaluasiTest.php tests/Feature/KasusEvaluasiViewTest.php tests/Feature/KasusEvaluasiSchemaTest.php tests/Unit/Domains/Kasus/Actions/Evaluasi/CatatEvaluasiActionTest.php`
Expected: semua PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Domains/Kasus/DataTransferObjects/CatatEvaluasiData.php app/Domains/Kasus/Actions/Evaluasi/ app/Http/Controllers/KasusEvaluasiController.php tests/Unit/Domains/Kasus/Actions/Evaluasi/
git commit -m "refactor(kasus): ekstrak Action sub-area Evaluasi, pakai KasusPolicy::isKonselor"
```

---

## Task 12: Pindahkan 11 View, Update SEMUA Nama View

**Files:**
- Move: `resources/views/kasus/*` → `resources/views/portals/kasus/*` (7 file)
- Move: `resources/views/admin/kasus/*` → `resources/views/portals/lembaga/kasus/*` (4 file)
- Modify: `app/Http/Controllers/Admin/KasusController.php`, `app/Http/Controllers/Admin/KasusAksesLogController.php`, `app/Http/Controllers/Admin/KasusTerhapusController.php`, `app/Http/Controllers/KasusController.php`
- Modify: `resources/views/portals/kasus/show.blade.php` (4 baris `@include`)
- Modify: test files dengan `assertViewIs('kasus.*')`/`assertViewIs('admin.kasus.*')` atau `->name()`

**Interfaces:**
- Consumes: file dari Task 5 (Manajemen) dan Task 6 (Pengajuan) yang view()-nya SENGAJA belum diubah namanya
- Produces: view final di lokasi baru

- [ ] **Step 1: Pindahkan 11 file view secara fisik**

```bash
mkdir -p resources/views/portals/kasus/partials resources/views/portals/lembaga/kasus
git mv resources/views/kasus/index.blade.php resources/views/portals/kasus/index.blade.php
git mv resources/views/kasus/create.blade.php resources/views/portals/kasus/create.blade.php
git mv resources/views/kasus/show.blade.php resources/views/portals/kasus/show.blade.php
git mv resources/views/kasus/partials/_tab-info.blade.php resources/views/portals/kasus/partials/_tab-info.blade.php
git mv resources/views/kasus/partials/_tab-sesi.blade.php resources/views/portals/kasus/partials/_tab-sesi.blade.php
git mv resources/views/kasus/partials/_tab-tugas.blade.php resources/views/portals/kasus/partials/_tab-tugas.blade.php
git mv resources/views/kasus/partials/_tab-evaluasi.blade.php resources/views/portals/kasus/partials/_tab-evaluasi.blade.php
git mv resources/views/admin/kasus/index.blade.php resources/views/portals/lembaga/kasus/index.blade.php
git mv resources/views/admin/kasus/triase.blade.php resources/views/portals/lembaga/kasus/triase.blade.php
git mv resources/views/admin/kasus/akses-log.blade.php resources/views/portals/lembaga/kasus/akses-log.blade.php
git mv resources/views/admin/kasus/terhapus.blade.php resources/views/portals/lembaga/kasus/terhapus.blade.php
rmdir resources/views/kasus/partials resources/views/kasus resources/views/admin/kasus 2>/dev/null || true
```

- [ ] **Step 2: Update 4 baris `@include` di `portals/kasus/show.blade.php` — HANYA baris ini, JANGAN sentuh baris `route(...)` di file yang sama**

Cari 4 baris persis:
```php
                @include('kasus.partials._tab-info')
```
```php
                    @include('kasus.partials._tab-sesi')
```
```php
                    @include('kasus.partials._tab-tugas')
```
```php
                    @include('kasus.partials._tab-evaluasi')
```
Ganti masing-masing jadi:
```php
                @include('portals.kasus.partials._tab-info')
```
```php
                    @include('portals.kasus.partials._tab-sesi')
```
```php
                    @include('portals.kasus.partials._tab-tugas')
```
```php
                    @include('portals.kasus.partials._tab-evaluasi')
```
JANGAN pakai `sed` blanket-replace satu file penuh — lakukan cari-ganti PER BARIS PERSIS seperti di atas (pakai Edit tool dengan `old_string`/`new_string` eksak, bukan regex global). File ini punya BANYAK `route('kasus.xxx', ...)` di baris lain (lihat §7 spec) yang TIDAK BOLEH ikut berubah.

- [ ] **Step 3: Update `view()` call di `app/Http/Controllers/KasusController.php` (3 baris)**

Cari dan ganti persis:
```php
        return view('kasus.index', [
```
jadi
```php
        return view('portals.kasus.index', [
```
```php
        return view('kasus.create', ['siswaList' => $siswaList]);
```
jadi
```php
        return view('portals.kasus.create', ['siswaList' => $siswaList]);
```
```php
        return view('kasus.show', [
```
jadi
```php
        return view('portals.kasus.show', [
```

- [ ] **Step 4: Update `view()` call di `Admin\KasusController` (2 baris)**

```php
        return view('admin.kasus.index', [
```
jadi
```php
        return view('portals.lembaga.kasus.index', [
```
```php
        return view('admin.kasus.triase', ['kasus' => $kasus, 'kandidat' => $kandidat]);
```
jadi
```php
        return view('portals.lembaga.kasus.triase', ['kasus' => $kasus, 'kandidat' => $kandidat]);
```

- [ ] **Step 5: Update `view()` call di `Admin\KasusAksesLogController` dan `Admin\KasusTerhapusController` (1 baris masing-masing)**

`KasusAksesLogController`: `return view('admin.kasus.akses-log', [` → `return view('portals.lembaga.kasus.akses-log', [`
`KasusTerhapusController`: `return view('admin.kasus.terhapus', [` → `return view('portals.lembaga.kasus.terhapus', [`

- [ ] **Step 6: Verifikasi tidak ada `route()` yang ikut ter-korupsi**

```bash
grep -rn "route('portals\." resources/views/portals/kasus resources/views/portals/lembaga/kasus
```
Expected: KOSONG. Kalau ADA hasil, itu bug — revert baris itu ke `route('kasus.xxx'` atau `route('admin.kasus.xxx'` (nama route TIDAK PERNAH berubah), verifikasi ulang.

- [ ] **Step 7: Cari & update test yang menguji nama view eksplisit**

```bash
grep -rln "assertViewIs('kasus\.\|assertViewIs('admin\.kasus\.\|->name())->toBe('kasus\.\|->name())->toBe('admin\.kasus\." tests
```
Update tiap kemunculan sesuai pemetaan Task 12 Step 3-5 di atas.

- [ ] **Step 8: Clear compiled view cache**

```bash
php artisan view:clear
```

- [ ] **Step 9: Jalankan SEMUA test Kasus (feature test yang render view, bukan cuma Action unit test)**

Run: `php artisan test tests/Feature/Kasus*.php tests/Feature/Admin/Kasus*.php tests/Feature/DashboardKasus*.php`
Expected: semua PASS, 0 failed. Kalau ada `RouteNotFoundException`, itu tanda ada `route()` yang ikut ter-ubah keliru — cari dan revert (Step 6).

- [ ] **Step 10: Commit**

```bash
git add -A
git commit -m "refactor(kasus): pindahkan 11 view ke portals/kasus & portals/lembaga/kasus, update semua nama view"
```

---

## Task 13: Verifikasi Akhir, Full Suite, Handoff Log

**Files:**
- Modify: `.agents/plans/2026-08-20-1800-master-refactor-domain-pattern.md` (§6 tabel sub-task, tandai selesai)
- Create: `.agents/logs/2026-08-20-2000-kasus-domain-migrasi.md`

- [ ] **Step 1: Verifikasi zero-leak lokasi lama**

```bash
grep -rln "App\\\\Models\\\\Kasus\b" --include="*.php" app database tests
grep -rln "App\\\\Models\\\\KasusConsent\|App\\\\Models\\\\KasusSesi\|App\\\\Models\\\\KasusTugas\|App\\\\Models\\\\KasusEvaluasi" --include="*.php" app database tests
grep -rln "App\\\\Enums\\\\StatusKasus" --include="*.php" app database tests
grep -rln "App\\\\Services\\\\KonselorAllocationResolver\|App\\\\Services\\\\TugasBatchGenerator" --include="*.php" app database tests
grep -rn "view('admin\.kasus\.\|view('kasus\." --include="*.php" app resources
```
Expected: SEMUA kosong (kecuali kalau ada penjelasan valid, dokumentasikan di handoff log).

- [ ] **Step 2: Verifikasi struktur akhir**

```bash
find app/Domains/Kasus -type f -name "*.php" | sort
```
Expected: 6 Model + 3 Enum + 1 DataTransferObjects lama (KonselorKandidat) + ~13 DataTransferObjects baru (Task 5-11) + 1 Policy + 2 Service + ~16 Action (3 Manajemen + 2 Pengajuan + 1 Consent + 2 Sesi + 2 Tugas + 2 Submission + 1 Evaluasi = 13 Action, sesuaikan dengan yang benar-benar dibuat).

- [ ] **Step 3: Jalankan seluruh test scoped Kasus + Policy sekaligus**

Run: `php artisan test tests/Feature/Kasus*.php tests/Feature/Admin/Kasus*.php tests/Feature/DashboardKasus*.php tests/Feature/Console/KirimReminderSesiTest.php tests/Feature/Console/TandaiTugasTerlewatTest.php tests/Unit/Enums/StatusKasusTest.php tests/Unit/Domains/Kasus`
Expected: semua PASS.

- [ ] **Step 4: Minta persetujuan user untuk full suite**

Tanyakan ke user ("Domain Kasus selesai dimigrasi — 6 model, 3 enum, 1 DTO, 2 service, 1 Policy baru, 13 Action, 11 view. Semua test scoped hijau. Jalankan full suite sekarang?"), jangan asumsikan izin.

- [ ] **Step 5: Jalankan full suite setelah disetujui**

Run: `php artisan test`
Expected: semua PASS. Baseline SEBELUM plan ini: 1875 passed. Jumlah SESUDAH plan ini HARUS lebih besar (test baru Task 4-11), 0 failed.

- [ ] **Step 6: Update tabel sub-task di master roadmap**

Di `.agents/plans/2026-08-20-1800-master-refactor-domain-pattern.md` §6, ganti baris kosong jadi:
```markdown
| 1 | Migrasi Domain Kasus | [`.agents/specs/2026-08-20-2000-kasus-domain-migrasi.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-20-2000-kasus-domain-migrasi.md) | [`.agents/plans/2026-08-20-2000-kasus-domain-migrasi.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-20-2000-kasus-domain-migrasi.md) | [`.agents/logs/2026-08-20-2000-kasus-domain-migrasi.md`](file:///d:/laragon/www/pintera-app/.agents/logs/2026-08-20-2000-kasus-domain-migrasi.md) | 🟢 SELESAI |
```

- [ ] **Step 7: Tulis handoff log**

Ke `.agents/logs/2026-08-20-2000-kasus-domain-migrasi.md`. Isi minimal: ringkasan yang dikerjakan (6 model, 3 enum, 1 DTO, 2 service, 1 Policy baru — jelaskan KasusPolicy konsolidasi 4 duplikasi, 13 Action, 11 view), hasil test per task, hasil full suite akhir, konfirmasi zero-behavior-change (kecuali KasusPolicy yang tetap identik hasil), dan status trait `AssertsKonselorPemegangKasus` (dihapus atau tidak, sesuai Task 9 Step 8).

- [ ] **Step 8: Commit**

```bash
git add .agents/plans/2026-08-20-1800-master-refactor-domain-pattern.md .agents/logs/2026-08-20-2000-kasus-domain-migrasi.md
git commit -m "docs(kasus): tutup migrasi domain Kasus - handoff log & update master roadmap"
```
