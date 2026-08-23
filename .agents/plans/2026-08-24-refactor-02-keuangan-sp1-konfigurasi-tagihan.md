# Migrasi Domain Keuangan Sub-project 1: Konfigurasi & Generasi Tagihan — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Memindahkan 8 model konfigurasi billing + `JenisTagihanController` (direfactor Action/DTO) + 2 service ke `app/Domains/Keuangan/*`, tanpa mengubah perilaku aplikasi.

**Architecture:** Model pindah fisik (hanya `$fillable`/`casts()`/relationship). Business logic controller (445 baris) diekstrak jadi 5 Action. Event `BillTypeActivated` yang sebelumnya di model `booted()` dipindah eksplisit ke `UpdateJenisTagihanAction` (diverifikasi hanya 1 call site nyata). Controller pindah namespace ke `Lembaga\Keuangan\`, view ke `portals/lembaga/keuangan/`.

**Tech Stack:** Laravel 12, Pest.

## Global Constraints

- **Zero-behavior-change** — pesan error, kode status HTTP, urutan validasi, format respons JSON/redirect HARUS identik kata-per-kata. Kalau ditemukan celah/inkonsistensi di kode lama, JANGAN diperbaiki diam-diam — laporkan ke user.
- Route NAME dan PATH tidak berubah. Hanya `use` statement controller di `routes/admin/keuangan.php` yang diganti.
- Model pindahan HANYA `$fillable`/`casts()`/relationship — TIDAK ADA method business logic (termasuk `booted()` — dihapus dari `JenisTagihan`, logic-nya pindah ke Action).
- `newFactory()` WAJIB untuk: `JenisTagihan`, `NominalTagihanJalur`, `TagihanItem`, `SkemaCicilan` (pakai `HasFactory`). **TIDAK ditambahkan** untuk: `NominalTagihanSiswa`, `JenisTagihanKeringanan`, `JenisTagihanSasaranGrup`, `JenisTagihanSasaranKriteria` (tidak pakai `HasFactory` sekarang).
- Referensi lintas-namespace pakai **FQCN inline**, BUKAN `use` statement tambahan di file yang TETAP di `app/Models/`.
- **Verifikasi grep WAJIB menyisir `app database tests`** (bukan cuma `app/Models`) — cari string `App\Models\{Model}` (bukan `{Model}::class`) supaya menangkap referensi inline FQCN yang tidak lewat `use` statement. Pelajaran eksplisit dari review Sub-project "Data Induk Sempit" sebelumnya.
- **Referensi implisit ANTAR model yang SAMA-SAMA pindah ke `Domains\Keuangan\Models` dalam sub-project ini TIDAK PERLU diubah** (tetap resolve benar karena berbagi namespace baru yang sama) — HANYA referensi dari file yang TETAP di `app/Models/` ke model yang pindah yang perlu FQCN.
- `JenisTagihanMonitoringController`, `TagihanBillingGenerator`, `TagihanController` (migrasi penuhnya), `Tagihan` model — TIDAK disentuh sama sekali (Sub-project 2).
- Baseline kode: commit `ed25f74` di branch `refactor-v1`. Kalau isi file beda signifikan dari yang dikutip plan, STOP, laporkan ke user.
- Tiap task: test SCOPED SEBELUM commit. Full suite HANYA task terakhir, izin eksplisit user dulu.

---

## Task 1: Pindahkan Model `JenisTagihan` (+ Ekstrak Event ke Catatan, Dihapus dari Model)

**Files:**
- Move: `app/Models/JenisTagihan.php` → `app/Domains/Keuangan/Models/JenisTagihan.php`
- Modify (88 file hasil grep `use App\Models\JenisTagihan;` — daftar di §4 spec `.agents/specs/2026-08-24-refactor-02-keuangan-sp1-konfigurasi-tagihan.md`, WAJIB grep ulang persis sebelum eksekusi karena daftar itu bisa berubah sejak spec ditulis)
- Modify (gotcha implisit, FQCN inline): `app/Models/BillingJobLog.php`, `app/Models/Tagihan.php` (3 referensi dalam 1 file: `JenisTagihan::class`, `TagihanItem::class`, `SkemaCicilan::class` — SEMUA diperbaiki di task ini karena baris-baris itu bertetangga, jangan buka file ini 3x di 3 task berbeda)

**Interfaces:**
- Produces: `App\Domains\Keuangan\Models\JenisTagihan` — dipakai Task 2-9, 12.

**PENTING:** event `BillTypeActivated` dispatch di `booted()` DIHAPUS dari model ini di task ini (bukan ditunda ke Task 12) — supaya model langsung bersih begitu pindah. TAPI perilaku dispatch event itu sendiri BELUM boleh hilang dari aplikasi — Task 12 WAJIB memindahkan logic yang sama persis ke `UpdateJenisTagihanAction`. Kalau plan dieksekusi task-by-task dengan jeda (subagent terpisah), ada window singkat di mana event TIDAK di-dispatch sama sekali (antara Task 1 selesai dan Task 12 selesai) — ini AMAN untuk kode produksi karena tidak ada deploy parsial di antara task, tapi WAJIB diperhatikan kalau urutan eksekusi task berubah (Task 12 tidak boleh dilewati/ditunda terpisah dari Task 1).

- [x] **Step 1: Pindahkan file fisik**

```bash
git mv app/Models/JenisTagihan.php app/Domains/Keuangan/Models/JenisTagihan.php
```

- [x] **Step 2: Ubah isi file — namespace, `newFactory()`, HAPUS `booted()`**

Timpa seluruh isi `app/Domains/Keuangan/Models/JenisTagihan.php` dengan:

```php
<?php

namespace App\Domains\Keuangan\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Lembaga;
use Database\Factories\JenisTagihanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisTagihan extends Model
{
    use BelongsToTenant, HasFactory;

    protected static function newFactory(): JenisTagihanFactory
    {
        return JenisTagihanFactory::new();
    }

    protected $table = 'jenis_tagihan';

    protected $attributes = [
        'mode' => 'manual',
        'is_active' => true,
    ];

    protected $fillable = [
        'lembaga_id', 'nama', 'kategori', 'bisa_dicicil', 'maks_cicilan',
        'priority_score', 'default_amount', 'mode',
        'tanggal_mulai', 'tanggal_selesai', 'tanggal_generate', 'hari_jatuh_tempo',
        'va_expire_hours', 'is_active', 'last_generated_period',
    ];

    protected function casts(): array
    {
        return [
            'bisa_dicicil' => 'boolean',
            'default_amount' => 'decimal:2',
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function nominalJalur(): HasMany
    {
        return $this->hasMany(NominalTagihanJalur::class);
    }

    public function tagihanItem(): HasMany
    {
        return $this->hasMany(TagihanItem::class);
    }

    public function sasaranGrup(): HasMany
    {
        return $this->hasMany(JenisTagihanSasaranGrup::class);
    }

    public function nominalTagihanSiswa(): HasMany
    {
        return $this->hasMany(NominalTagihanSiswa::class);
    }

    public function keringananRules(): HasMany
    {
        return $this->hasMany(JenisTagihanKeringanan::class);
    }
}
```

Catatan: relasi `nominalJalur()`, `tagihanItem()`, `sasaranGrup()`, `nominalTagihanSiswa()`, `keringananRules()` menunjuk ke model LAIN yang JUGA pindah ke `Domains\Keuangan\Models` di task-task berikutnya (Task 2-7) — TIDAK PERLU `use` statement tambahan atau FQCN, karena semua akan berbagi namespace `App\Domains\Keuangan\Models` yang sama begitu task 2-7 selesai. Kalau task ini dijalankan SEBELUM Task 2-7 (urutan normal), class-class itu belum ada di namespace baru — ini TIDAK error di level PHP (lazy class resolution untuk return type method biasa), tapi test yang benar-benar memanggil relasi ini akan gagal sampai Task 2-7 selesai. INI ALASAN kenapa urutan Task 1-8 penting dan sebaiknya dieksekusi berurutan tanpa test-scoped-luas di antara sebelum Task 8 selesai (test scoped SEMPIT per task tetap wajib, lihat Step 7).

- [x] **Step 3: Update `database/factories/JenisTagihanFactory.php`**

Ganti baris `use App\Models\JenisTagihan;` menjadi `use App\Domains\Keuangan\Models\JenisTagihan;`. Tidak ada perubahan lain.

- [x] **Step 4: Update seluruh file consumer lain (grep ulang dulu untuk daftar pasti)**

```bash
grep -rln "use App\\\\Models\\\\JenisTagihan;" --include="*.php" app database tests
```

Di SETIAP file hasil grep (KECUALI `database/factories/JenisTagihanFactory.php` yang sudah di Step 3), ganti baris `use App\Models\JenisTagihan;` menjadi `use App\Domains\Keuangan\Models\JenisTagihan;`. Tidak ada perubahan lain di file-file ini.

- [x] **Step 5: Perbaiki gotcha referensi implisit di `app/Models/BillingJobLog.php`**

Baca file, cari baris yang memanggil `JenisTagihan::class` tanpa `use` statement, ganti jadi `\App\Domains\Keuangan\Models\JenisTagihan::class` (FQCN inline).

- [x] **Step 6: Perbaiki gotcha referensi implisit di `app/Models/Tagihan.php` (3 referensi sekaligus)**

Baca file, cari SEMUA baris yang memanggil `JenisTagihan::class`, `TagihanItem::class`, atau `SkemaCicilan::class` tanpa `use` statement. Ganti masing-masing jadi FQCN inline:
- `JenisTagihan::class` → `\App\Domains\Keuangan\Models\JenisTagihan::class`
- `TagihanItem::class` → `\App\Domains\Keuangan\Models\TagihanItem::class`
- `SkemaCicilan::class` → `\App\Domains\Keuangan\Models\SkemaCicilan::class`

**PENTING:** `TagihanItem` dan `SkemaCicilan` BELUM pindah namespace-nya sampai Task 7 dan Task 8 selesai — kalau Task 6 ini dikerjakan sebelum Task 7/8, class `\App\Domains\Keuangan\Models\TagihanItem`/`SkemaCicilan` belum ada. Referensi FQCN yang "menunjuk ke depan" ini AMAN ditulis sekarang (PHP tidak resolve nama class sampai benar-benar dipanggil), tapi test yang memanggil relasi ini akan gagal sampai Task 7/8 selesai — WAJAR, bukan bug, jangan panik kalau test scoped Task 1 yang menyentuh `Tagihan.php` gagal sebagian sampai seluruh Task 1-8 selesai.

- [x] **Step 7: Jalankan test scoped SEMPIT (murni cek tidak ada "Class not found" untuk `use` statement, BUKAN test penuh — test penuh baru masuk akal setelah Task 8)**

```bash
php artisan test tests/Unit/JenisTagihanSeederTest.php database/seeders --dry-run 2>&1 | head -5
```

Kalau command di atas tidak relevan (Pest tidak punya `--dry-run` untuk seeder), cukup jalankan:

```bash
php artisan tinker --execute="echo class_exists(\App\Domains\Keuangan\Models\JenisTagihan::class) ? 'OK' : 'MISSING';"
```

Expected: `OK`. Ini verifikasi minimal bahwa class ter-load tanpa fatal error — test fungsional penuh menunggu Task 8 selesai (lihat Task 9).

- [x] **Step 8: Verifikasi tidak ada `use` lama tersisa**

```bash
grep -rln "use App\\\\Models\\\\JenisTagihan;" --include="*.php" app database tests
```
Expected: kosong.

- [x] **Step 9: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): pindah model JenisTagihan ke Domains\Keuangan\Models, hapus booted() event (pindah ke Action di Task 12)"
```

---

## Task 2: Pindahkan Model `NominalTagihanJalur`

**Files:**
- Move: `app/Models/NominalTagihanJalur.php` → `app/Domains/Keuangan/Models/NominalTagihanJalur.php`
- Modify: `database/factories/NominalTagihanJalurFactory.php` + seluruh file hasil grep `use App\Models\NominalTagihanJalur;` (grep ulang untuk daftar pasti)
- Modify (gotcha implisit): `app/Models/JenisTagihan.php` — TIDAK PERLU diubah (sudah sama-sama di `Domains\Keuangan\Models` sejak Task 1, relasi `nominalJalur()` otomatis resolve benar)

**Interfaces:**
- Produces: `App\Domains\Keuangan\Models\NominalTagihanJalur` — dipakai Task 9, 12.

- [x] **Step 1: Pindahkan file fisik**

```bash
git mv app/Models/NominalTagihanJalur.php app/Domains/Keuangan/Models/NominalTagihanJalur.php
```

- [x] **Step 2: Ubah isi file**

Timpa seluruh isi `app/Domains/Keuangan/Models/NominalTagihanJalur.php` dengan:

```php
<?php

namespace App\Domains\Keuangan\Models;

use App\Models\JalurPpdb;
use Database\Factories\NominalTagihanJalurFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NominalTagihanJalur extends Model
{
    use HasFactory;

    protected static function newFactory(): NominalTagihanJalurFactory
    {
        return NominalTagihanJalurFactory::new();
    }

    protected $table = 'nominal_tagihan_jalur';

    protected $fillable = ['jenis_tagihan_id', 'jalur_ppdb_id', 'nominal'];

    public function jenisTagihan(): BelongsTo
    {
        return $this->belongsTo(JenisTagihan::class);
    }

    public function jalurPpdb(): BelongsTo
    {
        return $this->belongsTo(JalurPpdb::class);
    }
}
```

- [x] **Step 3: Update `database/factories/NominalTagihanJalurFactory.php`**

Ganti `use App\Models\NominalTagihanJalur;` → `use App\Domains\Keuangan\Models\NominalTagihanJalur;`.

- [x] **Step 4: Update seluruh file consumer lain**

```bash
grep -rln "use App\\\\Models\\\\NominalTagihanJalur;" --include="*.php" app database tests
```

Di SETIAP file hasil grep (kecuali Factory di Step 3), ganti `use App\Models\NominalTagihanJalur;` → `use App\Domains\Keuangan\Models\NominalTagihanJalur;`.

- [x] **Step 5: Verifikasi**

```bash
grep -rln "use App\\\\Models\\\\NominalTagihanJalur;" --include="*.php" app database tests
```
Expected: kosong.

- [x] **Step 6: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): pindah model NominalTagihanJalur ke Domains\Keuangan\Models"
```

---

## Task 3: Pindahkan Model `NominalTagihanSiswa`

**Files:**
- Move: `app/Models/NominalTagihanSiswa.php` → `app/Domains/Keuangan/Models/NominalTagihanSiswa.php`
- Modify: seluruh file hasil grep `use App\Models\NominalTagihanSiswa;` (TIDAK ADA factory — model ini tidak pakai `HasFactory`)

**Interfaces:**
- Produces: `App\Domains\Keuangan\Models\NominalTagihanSiswa` — dipakai Task 9.

- [x] **Step 1: Pindahkan file fisik**

```bash
git mv app/Models/NominalTagihanSiswa.php app/Domains/Keuangan/Models/NominalTagihanSiswa.php
```

- [x] **Step 2: Ubah isi file (TANPA `newFactory()` — model ini tidak pakai `HasFactory`)**

Timpa seluruh isi `app/Domains/Keuangan/Models/NominalTagihanSiswa.php` dengan:

```php
<?php

namespace App\Domains\Keuangan\Models;

use App\Models\Siswa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NominalTagihanSiswa extends Model
{
    protected $table = 'nominal_tagihan_siswa';

    protected $fillable = ['jenis_tagihan_id', 'siswa_id', 'nominal'];

    protected function casts(): array
    {
        return [
            'nominal' => 'decimal:2',
        ];
    }

    public function jenisTagihan(): BelongsTo
    {
        return $this->belongsTo(JenisTagihan::class);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }
}
```

- [x] **Step 3: Update seluruh file consumer**

```bash
grep -rln "use App\\\\Models\\\\NominalTagihanSiswa;" --include="*.php" app database tests
```

Di SETIAP file hasil grep, ganti `use App\Models\NominalTagihanSiswa;` → `use App\Domains\Keuangan\Models\NominalTagihanSiswa;`.

- [x] **Step 4: Verifikasi**

```bash
grep -rln "use App\\\\Models\\\\NominalTagihanSiswa;" --include="*.php" app database tests
```
Expected: kosong.

- [x] **Step 5: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): pindah model NominalTagihanSiswa ke Domains\Keuangan\Models"
```

---

## Task 4: Pindahkan Model `JenisTagihanKeringanan`

**Files:**
- Move: `app/Models/JenisTagihanKeringanan.php` → `app/Domains/Keuangan/Models/JenisTagihanKeringanan.php`
- Modify: seluruh file hasil grep `use App\Models\JenisTagihanKeringanan;`
- Modify (gotcha implisit): `app/Models/KategoriKeringanan.php` — FQCN inline

**Interfaces:**
- Produces: `App\Domains\Keuangan\Models\JenisTagihanKeringanan` — dipakai Task 9.

- [x] **Step 1: Pindahkan file fisik**

```bash
git mv app/Models/JenisTagihanKeringanan.php app/Domains/Keuangan/Models/JenisTagihanKeringanan.php
```

- [x] **Step 2: Ubah isi file**

Timpa seluruh isi `app/Domains/Keuangan/Models/JenisTagihanKeringanan.php` dengan:

```php
<?php
// app/Domains/Keuangan/Models/JenisTagihanKeringanan.php

namespace App\Domains\Keuangan\Models;

use App\Models\KategoriKeringanan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JenisTagihanKeringanan extends Model
{
    protected $table = 'jenis_tagihan_keringanan';

    protected $fillable = ['jenis_tagihan_id', 'kategori_keringanan_id', 'tipe_potongan', 'nilai', 'keterangan'];

    protected function casts(): array
    {
        return [
            'nilai' => 'decimal:2',
        ];
    }

    public function jenisTagihan(): BelongsTo
    {
        return $this->belongsTo(JenisTagihan::class);
    }

    public function kategoriKeringanan(): BelongsTo
    {
        return $this->belongsTo(KategoriKeringanan::class);
    }
}
```

- [x] **Step 3: Update seluruh file consumer**

```bash
grep -rln "use App\\\\Models\\\\JenisTagihanKeringanan;" --include="*.php" app database tests
```

Di SETIAP file hasil grep, ganti `use App\Models\JenisTagihanKeringanan;` → `use App\Domains\Keuangan\Models\JenisTagihanKeringanan;`.

- [x] **Step 4: Perbaiki gotcha referensi implisit di `app/Models/KategoriKeringanan.php`**

Baca file, cari baris yang memanggil `JenisTagihanKeringanan::class` tanpa `use` statement, ganti jadi `\App\Domains\Keuangan\Models\JenisTagihanKeringanan::class` (FQCN inline).

- [x] **Step 5: Verifikasi**

```bash
grep -rln "use App\\\\Models\\\\JenisTagihanKeringanan;" --include="*.php" app database tests
```
Expected: kosong.

- [x] **Step 6: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): pindah model JenisTagihanKeringanan ke Domains\Keuangan\Models"
```

---

## Task 5: Pindahkan Model `JenisTagihanSasaranGrup`

**Files:**
- Move: `app/Models/JenisTagihanSasaranGrup.php` → `app/Domains/Keuangan/Models/JenisTagihanSasaranGrup.php`
- Modify: seluruh file hasil grep `use App\Models\JenisTagihanSasaranGrup;`

**Interfaces:**
- Produces: `App\Domains\Keuangan\Models\JenisTagihanSasaranGrup` — dipakai Task 6, 9.

- [x] **Step 1: Pindahkan file fisik**

```bash
git mv app/Models/JenisTagihanSasaranGrup.php app/Domains/Keuangan/Models/JenisTagihanSasaranGrup.php
```

- [x] **Step 2: Ubah isi file**

Timpa seluruh isi `app/Domains/Keuangan/Models/JenisTagihanSasaranGrup.php` dengan:

```php
<?php

namespace App\Domains\Keuangan\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisTagihanSasaranGrup extends Model
{
    protected $table = 'jenis_tagihan_sasaran_grup';

    protected $fillable = ['jenis_tagihan_id', 'tipe', 'nominal'];

    protected function casts(): array
    {
        return [
            'nominal' => 'decimal:2',
        ];
    }

    public function jenisTagihan(): BelongsTo
    {
        return $this->belongsTo(JenisTagihan::class);
    }

    public function kriteria(): HasMany
    {
        return $this->hasMany(JenisTagihanSasaranKriteria::class, 'jenis_tagihan_sasaran_grup_id');
    }
}
```

- [x] **Step 3: Update seluruh file consumer**

```bash
grep -rln "use App\\\\Models\\\\JenisTagihanSasaranGrup;" --include="*.php" app database tests
```

Di SETIAP file hasil grep, ganti `use App\Models\JenisTagihanSasaranGrup;` → `use App\Domains\Keuangan\Models\JenisTagihanSasaranGrup;`.

- [x] **Step 4: Verifikasi**

```bash
grep -rln "use App\\\\Models\\\\JenisTagihanSasaranGrup;" --include="*.php" app database tests
```
Expected: kosong.

- [x] **Step 5: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): pindah model JenisTagihanSasaranGrup ke Domains\Keuangan\Models"
```

---

## Task 6: Pindahkan Model `JenisTagihanSasaranKriteria`

**Files:**
- Move: `app/Models/JenisTagihanSasaranKriteria.php` → `app/Domains/Keuangan/Models/JenisTagihanSasaranKriteria.php`
- Modify: seluruh file hasil grep `use App\Models\JenisTagihanSasaranKriteria;`

**Interfaces:**
- Produces: `App\Domains\Keuangan\Models\JenisTagihanSasaranKriteria` — dipakai Task 9.

- [x] **Step 1: Pindahkan file fisik**

```bash
git mv app/Models/JenisTagihanSasaranKriteria.php app/Domains/Keuangan/Models/JenisTagihanSasaranKriteria.php
```

- [x] **Step 2: Ubah isi file**

Timpa seluruh isi `app/Domains/Keuangan/Models/JenisTagihanSasaranKriteria.php` dengan:

```php
<?php

namespace App\Domains\Keuangan\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JenisTagihanSasaranKriteria extends Model
{
    protected $table = 'jenis_tagihan_sasaran_kriteria';

    protected $fillable = ['jenis_tagihan_sasaran_grup_id', 'field', 'operator', 'value'];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public function grup(): BelongsTo
    {
        return $this->belongsTo(JenisTagihanSasaranGrup::class, 'jenis_tagihan_sasaran_grup_id');
    }
}
```

- [x] **Step 3: Update seluruh file consumer**

```bash
grep -rln "use App\\\\Models\\\\JenisTagihanSasaranKriteria;" --include="*.php" app database tests
```

Di SETIAP file hasil grep, ganti `use App\Models\JenisTagihanSasaranKriteria;` → `use App\Domains\Keuangan\Models\JenisTagihanSasaranKriteria;`.

- [x] **Step 4: Verifikasi**

```bash
grep -rln "use App\\\\Models\\\\JenisTagihanSasaranKriteria;" --include="*.php" app database tests
```
Expected: kosong.

- [x] **Step 5: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): pindah model JenisTagihanSasaranKriteria ke Domains\Keuangan\Models"
```

---

## Task 7: Pindahkan Model `TagihanItem`

**Files:**
- Move: `app/Models/TagihanItem.php` → `app/Domains/Keuangan/Models/TagihanItem.php`
- Modify: `database/factories/TagihanItemFactory.php` + seluruh file hasil grep `use App\Models\TagihanItem;`

**Interfaces:**
- Produces: `App\Domains\Keuangan\Models\TagihanItem` — dipakai Task 9.
- `tagihan()` tetap `belongsTo(\App\Models\Tagihan::class)` — `Tagihan` TIDAK pindah (Sub-project 2), jadi ini jadi referensi lintas-domain yang WAJIB pakai FQCN inline (bukan `use`, konsisten dengan pola preseden Data Induk Sempit untuk relasi ke model di luar domain).

- [x] **Step 1: Pindahkan file fisik**

```bash
git mv app/Models/TagihanItem.php app/Domains/Keuangan/Models/TagihanItem.php
```

- [x] **Step 2: Ubah isi file**

Timpa seluruh isi `app/Domains/Keuangan/Models/TagihanItem.php` dengan:

```php
<?php

namespace App\Domains\Keuangan\Models;

use Database\Factories\TagihanItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TagihanItem extends Model
{
    use HasFactory;

    protected static function newFactory(): TagihanItemFactory
    {
        return TagihanItemFactory::new();
    }

    protected $table = 'tagihan_item';

    protected $fillable = ['tagihan_id', 'jenis_tagihan_id', 'jumlah'];

    public function tagihan(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Tagihan::class);
    }

    public function jenisTagihan(): BelongsTo
    {
        return $this->belongsTo(JenisTagihan::class);
    }
}
```

- [x] **Step 3: Update `database/factories/TagihanItemFactory.php`**

Ganti `use App\Models\TagihanItem;` → `use App\Domains\Keuangan\Models\TagihanItem;`.

- [x] **Step 4: Update seluruh file consumer lain**

```bash
grep -rln "use App\\\\Models\\\\TagihanItem;" --include="*.php" app database tests
```

Di SETIAP file hasil grep (kecuali Factory di Step 3), ganti `use App\Models\TagihanItem;` → `use App\Domains\Keuangan\Models\TagihanItem;`.

- [x] **Step 5: Verifikasi**

```bash
grep -rln "use App\\\\Models\\\\TagihanItem;" --include="*.php" app database tests
```
Expected: kosong.

- [x] **Step 6: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): pindah model TagihanItem ke Domains\Keuangan\Models"
```

---

## Task 8: Pindahkan Model `SkemaCicilan`

**Files:**
- Move: `app/Models/SkemaCicilan.php` → `app/Domains/Keuangan/Models/SkemaCicilan.php`
- Modify: `database/factories/SkemaCicilanFactory.php` + seluruh file hasil grep `use App\Models\SkemaCicilan;` (TERMASUK `app/Http/Controllers/Admin/TagihanController.php` — file ini TIDAK dimigrasi sub-project ini, tapi `use` statement-nya WAJIB diupdate karena mengimpor model yang pindah)
- Modify (gotcha implisit): `app/Models/Cicilan.php` — FQCN inline

**Interfaces:**
- Produces: `App\Domains\Keuangan\Models\SkemaCicilan` — model terakhir sub-project ini.
- `tagihan()` tetap referensi ke `\App\Models\Tagihan::class` (Sub-project 2, FQCN inline). `cicilan()` tetap referensi ke `\App\Models\Cicilan::class` (Sub-project 4, FQCN inline) — DUA-DUANYA lintas-domain, DUA-DUANYA FQCN inline.

- [x] **Step 1: Pindahkan file fisik**

```bash
git mv app/Models/SkemaCicilan.php app/Domains/Keuangan/Models/SkemaCicilan.php
```

- [x] **Step 2: Ubah isi file**

Timpa seluruh isi `app/Domains/Keuangan/Models/SkemaCicilan.php` dengan:

```php
<?php

namespace App\Domains\Keuangan\Models;

use App\Models\User;
use Database\Factories\SkemaCicilanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SkemaCicilan extends Model
{
    use HasFactory;

    protected static function newFactory(): SkemaCicilanFactory
    {
        return SkemaCicilanFactory::new();
    }

    protected $table = 'skema_cicilan';

    protected $fillable = ['tagihan_id', 'jumlah_termin', 'dibuat_oleh', 'dibuat_oleh_user_id'];

    public function tagihan(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Tagihan::class);
    }

    public function cicilan(): HasMany
    {
        return $this->hasMany(\App\Models\Cicilan::class)->orderBy('urutan');
    }

    public function dibuatOlehUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh_user_id');
    }
}
```

- [x] **Step 3: Update `database/factories/SkemaCicilanFactory.php`**

Ganti `use App\Models\SkemaCicilan;` → `use App\Domains\Keuangan\Models\SkemaCicilan;`.

- [x] **Step 4: Update seluruh file consumer lain (termasuk `TagihanController.php` yang bukan bagian migrasi ini)**

```bash
grep -rln "use App\\\\Models\\\\SkemaCicilan;" --include="*.php" app database tests
```

Di SETIAP file hasil grep (kecuali Factory di Step 3), ganti `use App\Models\SkemaCicilan;` → `use App\Domains\Keuangan\Models\SkemaCicilan;`. Ini termasuk `app/Http/Controllers/Admin/TagihanController.php` — HANYA baris `use` yang diganti, method/logic di dalamnya TIDAK disentuh sama sekali (controller ini bukan bagian migrasi sub-project ini).

- [x] **Step 5: Perbaiki gotcha referensi implisit di `app/Models/Cicilan.php`**

Baca file, cari baris yang memanggil `SkemaCicilan::class` tanpa `use` statement, ganti jadi `\App\Domains\Keuangan\Models\SkemaCicilan::class` (FQCN inline).

- [x] **Step 6: Verifikasi**

```bash
grep -rln "use App\\\\Models\\\\SkemaCicilan;" --include="*.php" app database tests
```
Expected: kosong.

- [x] **Step 7: Jalankan test scoped GABUNGAN untuk seluruh 8 model (Task 1-8 SEKARANG selesai semua)**

```bash
php artisan test tests/Unit/TagihanSeederTest.php tests/Unit/NominalTagihanJalurSeederTest.php tests/Unit/JenisTagihanSeederTest.php tests/Feature/Keuangan/TagihanNominalResolverTest.php tests/Feature/Keuangan/JenisTagihanSasaranMatcherTest.php tests/Feature/Keuangan/JenisTagihanSasaranGrupTest.php tests/Feature/Keuangan/NominalTagihanSiswaTest.php tests/Feature/Keuangan/KeringananTest.php tests/Feature/Admin/SkemaCicilanTest.php
```
Expected: semua PASS, 0 failed, 0 error. Kalau ADA yang gagal dengan "Class not found", cek lagi Task 1-8 — kemungkinan ada `use` statement yang kelewat.

- [x] **Step 8: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): pindah model SkemaCicilan ke Domains\Keuangan\Models, update TagihanController use-statement (cross-sub-project touch)"
```

---

## Task 9: Pindahkan 2 Service (`JenisTagihanSasaranMatcher`, `TagihanNominalResolver`)

**Files:**
- Move: `app/Services/JenisTagihanSasaranMatcher.php` → `app/Domains/Keuangan/Services/JenisTagihanSasaranMatcher.php`
- Move: `app/Services/TagihanNominalResolver.php` → `app/Domains/Keuangan/Services/TagihanNominalResolver.php`
- Modify: seluruh file hasil grep `use App\Services\JenisTagihanSasaranMatcher;` dan `use App\Services\TagihanNominalResolver;` (TERMASUK `app/Services/TagihanBillingGenerator.php` — Sub-project 2 territory, TIDAK dimigrasi, tapi `use`-nya WAJIB diupdate)

**Interfaces:**
- Produces: `App\Domains\Keuangan\Services\JenisTagihanSasaranMatcher`, `App\Domains\Keuangan\Services\TagihanNominalResolver` — dipakai Task 12 (`ProsesJenisTagihanBillingAction`), dan oleh `TagihanBillingGenerator` (Sub-project 2, tidak dimigrasi tapi tetap konsumen).

Isi kedua service SAAT INI (baseline — baca dulu untuk konfirmasi sebelum edit) sudah dikutip lengkap di eksplorasi spec ini; hanya `use App\Models\{JenisTagihan,JenisTagihanSasaranGrup,JenisTagihanSasaranKriteria,NominalTagihanSiswa,JenisTagihanKeringanan};` yang berubah ke `Domains\Keuangan\Models`, `use App\Models\{Siswa,SiswaKeringanan};` TETAP (di luar domain, model fondasi).

- [x] **Step 1: Pindahkan file fisik**

```bash
git mv app/Services/JenisTagihanSasaranMatcher.php app/Domains/Keuangan/Services/JenisTagihanSasaranMatcher.php
git mv app/Services/TagihanNominalResolver.php app/Domains/Keuangan/Services/TagihanNominalResolver.php
```

- [x] **Step 2: Ubah isi `JenisTagihanSasaranMatcher.php`**

Timpa seluruh isi `app/Domains/Keuangan/Services/JenisTagihanSasaranMatcher.php` dengan:

```php
<?php
// app/Domains/Keuangan/Services/JenisTagihanSasaranMatcher.php

namespace App\Domains\Keuangan\Services;

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\JenisTagihanSasaranGrup;
use App\Domains\Keuangan\Models\JenisTagihanSasaranKriteria;
use App\Models\Scopes\TenantScope;
use App\Models\Siswa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class JenisTagihanSasaranMatcher
{
    /**
     * @return Collection<int, Siswa>
     */
    public function resolveTargetSiswa(JenisTagihan $jenisTagihan): Collection
    {
        $sasaranGrups = $jenisTagihan->sasaranGrup()->where('tipe', 'sasaran')->with('kriteria')->get();

        $query = Siswa::withoutGlobalScope(TenantScope::class)
            ->with('kelas')
            ->where('lembaga_id', $jenisTagihan->lembaga_id);

        if ($sasaranGrups->isNotEmpty()) {
            $query->where(function (Builder $outer) use ($sasaranGrups) {
                foreach ($sasaranGrups as $grup) {
                    $outer->orWhere(function (Builder $inner) use ($grup) {
                        foreach ($grup->kriteria as $kriteria) {
                            $this->applyKriteriaToQuery($inner, $kriteria);
                        }
                    });
                }
            });
        }

        return $query->get();
    }

    public function countTotalSiswaPool(JenisTagihan $jenisTagihan): int
    {
        return Siswa::withoutGlobalScope(TenantScope::class)
            ->where('lembaga_id', $jenisTagihan->lembaga_id)
            ->count();
    }

    public function siswaMatchesGrup(Siswa $siswa, JenisTagihanSasaranGrup $grup): bool
    {
        foreach ($grup->kriteria as $kriteria) {
            if (! $this->siswaMatchesKriteria($siswa, $kriteria)) {
                return false;
            }
        }

        return true;
    }

    public function siswaMatchesJenisTagihan(Siswa $siswa, JenisTagihan $jenisTagihan): bool
    {
        if ($siswa->lembaga_id !== $jenisTagihan->lembaga_id) {
            return false;
        }

        $sasaranGrups = $jenisTagihan->sasaranGrup()->where('tipe', 'sasaran')->with('kriteria')->get();

        if ($sasaranGrups->isEmpty()) {
            return true;
        }

        foreach ($sasaranGrups as $grup) {
            if ($this->siswaMatchesGrup($siswa, $grup)) {
                return true;
            }
        }

        return false;
    }

    private function applyKriteriaToQuery(Builder $query, JenisTagihanSasaranKriteria $kriteria): void
    {
        $values = $kriteria->value;
        $isIn = $kriteria->operator === 'in';

        switch ($kriteria->field) {
            case 'lembaga':
                $isIn ? $query->whereIn('lembaga_id', $values) : $query->whereNotIn('lembaga_id', $values);
                break;
            case 'kelas':
                if ($isIn) {
                    $query->whereIn('kelas_id', $values);
                } else {
                    // A siswa with no kelas assigned does not have any of the
                    // excluded kelas, so it must match `not_in` — mirroring
                    // siswaMatchesKriteria()'s PHP-side null handling. Grouped
                    // in a nested where() so this stays AND-scoped to the
                    // enclosing grup regardless of outer OR nesting.
                    $query->where(function (Builder $q) use ($values) {
                        $q->whereNotIn('kelas_id', $values)->orWhereNull('kelas_id');
                    });
                }
                break;
            case 'jenis_kelamin':
                $isIn ? $query->whereIn('jenis_kelamin', $values) : $query->whereNotIn('jenis_kelamin', $values);
                break;
            case 'status_siswa':
                $isIn ? $query->whereIn('status', $values) : $query->whereNotIn('status', $values);
                break;
            case 'tahun_ajaran':
                $isIn
                    ? $query->whereHas('kelas', fn (Builder $k) => $k->whereIn('tahun_ajaran_id', $values))
                    : $query->whereDoesntHave('kelas', fn (Builder $k) => $k->whereIn('tahun_ajaran_id', $values));
                break;
            case 'tingkat':
                $isIn
                    ? $query->whereHas('kelas', fn (Builder $k) => $k->whereIn('tingkat', $values))
                    : $query->whereDoesntHave('kelas', fn (Builder $k) => $k->whereIn('tingkat', $values));
                break;
        }
    }

    private function siswaMatchesKriteria(Siswa $siswa, JenisTagihanSasaranKriteria $kriteria): bool
    {
        $actual = match ($kriteria->field) {
            'lembaga' => $siswa->lembaga_id,
            'kelas' => $siswa->kelas_id,
            'jenis_kelamin' => $siswa->jenis_kelamin,
            'status_siswa' => $siswa->status->value,
            'tahun_ajaran' => $siswa->kelas?->tahun_ajaran_id,
            'tingkat' => $siswa->kelas?->tingkat,
        };

        $inList = in_array($actual, $kriteria->value);

        return $kriteria->operator === 'in' ? $inList : ! $inList;
    }
}
```

- [x] **Step 3: Ubah isi `TagihanNominalResolver.php`**

Timpa seluruh isi `app/Domains/Keuangan/Services/TagihanNominalResolver.php` dengan:

```php
<?php
// app/Domains/Keuangan/Services/TagihanNominalResolver.php

namespace App\Domains\Keuangan\Services;

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\JenisTagihanKeringanan;
use App\Domains\Keuangan\Models\NominalTagihanSiswa;
use App\Models\Siswa;
use App\Models\SiswaKeringanan;

class TagihanNominalResolver
{
    public function __construct(private readonly JenisTagihanSasaranMatcher $matcher)
    {
    }

    /**
     * @return array{nominal: float, discount_amount: float, discount_type: ?string}
     */
    public function resolve(Siswa $siswa, JenisTagihan $jenisTagihan): array
    {
        $nominal = $this->resolveNominal($siswa, $jenisTagihan);
        [$discountAmount, $discountType] = $this->resolveDiscount($siswa, $jenisTagihan, $nominal);

        return [
            'nominal' => $nominal,
            'discount_amount' => $discountAmount,
            'discount_type' => $discountType,
        ];
    }

    private function resolveNominal(Siswa $siswa, JenisTagihan $jenisTagihan): float
    {
        $override = NominalTagihanSiswa::where('jenis_tagihan_id', $jenisTagihan->id)
            ->where('siswa_id', $siswa->id)
            ->first();

        if ($override) {
            return (float) $override->nominal;
        }

        $tarifGrups = $jenisTagihan->sasaranGrup()->where('tipe', 'tarif')->with('kriteria')->orderBy('id')->get();

        foreach ($tarifGrups as $grup) {
            if ($this->matcher->siswaMatchesGrup($siswa, $grup)) {
                return (float) $grup->nominal;
            }
        }

        return (float) ($jenisTagihan->default_amount ?? 0);
    }

    /**
     * @return array{0: float, 1: ?string}
     */
    private function resolveDiscount(Siswa $siswa, JenisTagihan $jenisTagihan, float $nominal): array
    {
        $today = now()->toDateString();

        $kategoriIds = SiswaKeringanan::where('siswa_id', $siswa->id)
            ->where('berlaku_dari', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('berlaku_sampai')->orWhere('berlaku_sampai', '>=', $today);
            })
            ->pluck('kategori_keringanan_id');

        if ($kategoriIds->isEmpty()) {
            return [0.0, null];
        }

        $rules = JenisTagihanKeringanan::where('jenis_tagihan_id', $jenisTagihan->id)
            ->whereIn('kategori_keringanan_id', $kategoriIds)
            ->get();

        $bestAmount = 0.0;
        $bestType = null;

        foreach ($rules as $rule) {
            $amount = $rule->tipe_potongan === 'persen'
                ? round($nominal * ((float) $rule->nilai) / 100, 2)
                : (float) $rule->nilai;

            if ($amount > $bestAmount) {
                $bestAmount = $amount;
                $bestType = $rule->tipe_potongan;
            }
        }

        return [$bestAmount, $bestType];
    }
}
```

- [x] **Step 4: Update seluruh file consumer**

```bash
grep -rln "use App\\\\Services\\\\JenisTagihanSasaranMatcher;" --include="*.php" app database tests
grep -rln "use App\\\\Services\\\\TagihanNominalResolver;" --include="*.php" app database tests
```

Di SETIAP file hasil kedua grep di atas, ganti:
- `use App\Services\JenisTagihanSasaranMatcher;` → `use App\Domains\Keuangan\Services\JenisTagihanSasaranMatcher;`
- `use App\Services\TagihanNominalResolver;` → `use App\Domains\Keuangan\Services\TagihanNominalResolver;`

Ini termasuk `app/Services/TagihanBillingGenerator.php` dan `app/Http/Controllers/Admin/JenisTagihanController.php` (yang terakhir ini akan diganti total di Task 12, tapi kalau Task 9 dikerjakan sebelum Task 12, `use` statement-nya tetap harus diupdate dulu supaya tidak ada window rusak).

- [x] **Step 5: Verifikasi**

```bash
grep -rln "use App\\\\Services\\\\JenisTagihanSasaranMatcher;\|use App\\\\Services\\\\TagihanNominalResolver;" --include="*.php" app database tests
```
Expected: kosong.

- [x] **Step 6: Jalankan test scoped**

```bash
php artisan test tests/Feature/Keuangan/JenisTagihanSasaranMatcherTest.php tests/Feature/Keuangan/TagihanNominalResolverTest.php tests/Feature/Keuangan/TagihanBillingGeneratorTest.php
```
Expected: semua PASS, 0 failed, 0 error.

- [x] **Step 7: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): pindah JenisTagihanSasaranMatcher + TagihanNominalResolver ke Domains\Keuangan\Services"
```

---

## Task 10: Buat DTO + 5 Action dari `JenisTagihanController`

**Files:**
- Create: `app/Domains/Keuangan/DataTransferObjects/JenisTagihanData.php`
- Create: `app/Domains/Keuangan/Actions/JenisTagihan/CreateJenisTagihanAction.php`
- Create: `app/Domains/Keuangan/Actions/JenisTagihan/UpdateJenisTagihanAction.php`
- Create: `app/Domains/Keuangan/Actions/JenisTagihan/DeleteJenisTagihanAction.php`
- Create: `app/Domains/Keuangan/Actions/JenisTagihan/ProsesJenisTagihanBillingAction.php`
- Create: `app/Domains/Keuangan/Actions/JenisTagihan/SimpanNominalJenisTagihanAction.php`

**Interfaces:**
- Consumes: `App\Domains\Keuangan\Models\{JenisTagihan,NominalTagihanJalur}` (Task 1-2), `App\Domains\Keuangan\Services\JenisTagihanSasaranMatcher` (Task 9).
- Produces: 5 Action + 1 DTO — dipakai Task 12 (controller baru).

**Catatan desain:** payload `billing` (`sasaran`/`tarif`/`keringanan`) TETAP array mentah (bukan dibungkus DTO baru) — bentuknya deeply-nested dan opsional, memaksakan DTO kaku di sini nambah kompleksitas tanpa manfaat nyata (YAGNI). `JenisTagihanData` HANYA membungkus field dasar (bukan billing).

- [x] **Step 1: Buat DTO**

`app/Domains/Keuangan/DataTransferObjects/JenisTagihanData.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\DataTransferObjects;

final readonly class JenisTagihanData
{
    public function __construct(
        public ?int $lembagaId,
        public string $nama,
        public string $kategori,
        public bool $bisaDicicil,
        public ?int $maksCicilan,
        public ?float $defaultAmount,
        public ?string $mode,
        public ?string $tanggalMulai,
        public ?string $tanggalSelesai,
        public ?int $tanggalGenerate,
        public ?int $hariJatuhTempo,
        public bool $isActive,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            lembagaId: isset($data['lembaga_id']) ? (int) $data['lembaga_id'] : null,
            nama: $data['nama'],
            kategori: $data['kategori'],
            bisaDicicil: (bool) $data['bisa_dicicil'],
            maksCicilan: isset($data['maks_cicilan']) ? (int) $data['maks_cicilan'] : null,
            defaultAmount: isset($data['default_amount']) ? (float) $data['default_amount'] : null,
            mode: $data['mode'] ?? null,
            tanggalMulai: $data['tanggal_mulai'] ?? null,
            tanggalSelesai: $data['tanggal_selesai'] ?? null,
            tanggalGenerate: isset($data['tanggal_generate']) ? (int) $data['tanggal_generate'] : null,
            hariJatuhTempo: isset($data['hari_jatuh_tempo']) ? (int) $data['hari_jatuh_tempo'] : null,
            isActive: (bool) $data['is_active'],
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'lembaga_id' => $this->lembagaId,
            'nama' => $this->nama,
            'kategori' => $this->kategori,
            'bisa_dicicil' => $this->bisaDicicil,
            'maks_cicilan' => $this->maksCicilan,
            'default_amount' => $this->defaultAmount,
            'mode' => $this->mode,
            'tanggal_mulai' => $this->tanggalMulai,
            'tanggal_selesai' => $this->tanggalSelesai,
            'tanggal_generate' => $this->tanggalGenerate,
            'hari_jatuh_tempo' => $this->hariJatuhTempo,
            'is_active' => $this->isActive,
        ], fn ($value) => $value !== null);
    }
}
```

**Catatan `toArray()`:** `array_filter` dengan `$value !== null` supaya field yang memang `null` (mis. `lembaga_id` untuk aktor non-yayasan yang tidak override) TIDAK ikut dikirim ke `update()`/`create()` — mencegah field ke-overwrite jadi `null` secara tidak sengaja. `is_active`/`bisa_dicicil` selalu `bool` (tidak pernah `null` dari `$request->boolean()`), aman selalu ikut.

- [x] **Step 2: Buat `CreateJenisTagihanAction`**

`app/Domains/Keuangan/Actions/JenisTagihan/CreateJenisTagihanAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\Actions\JenisTagihan;

use App\Domains\Keuangan\DataTransferObjects\JenisTagihanData;
use App\Domains\Keuangan\Models\JenisTagihan;
use Illuminate\Support\Facades\DB;

final class CreateJenisTagihanAction
{
    public function execute(JenisTagihanData $data, ?array $billing): JenisTagihan
    {
        return DB::transaction(function () use ($data, $billing) {
            $jenisTagihan = JenisTagihan::create($data->toArray());

            if ($billing !== null) {
                app(SyncJenisTagihanBillingConfigAction::class)->execute($jenisTagihan, $billing);
            }

            return $jenisTagihan;
        });
    }
}
```

- [x] **Step 3: Buat `SyncJenisTagihanBillingConfigAction` (dipakai Create & Update)**

`app/Domains/Keuangan/Actions/JenisTagihan/SyncJenisTagihanBillingConfigAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\Actions\JenisTagihan;

use App\Domains\Keuangan\Models\JenisTagihan;

final class SyncJenisTagihanBillingConfigAction
{
    public function execute(JenisTagihan $jenisTagihan, array $billing): void
    {
        $jenisTagihan->sasaranGrup()->delete();
        $jenisTagihan->keringananRules()->delete();

        foreach ($billing['sasaran'] ?? [] as $grupData) {
            $grup = $jenisTagihan->sasaranGrup()->create(['tipe' => 'sasaran']);
            foreach ($grupData['kriteria'] as $kriteriaData) {
                $grup->kriteria()->create($kriteriaData);
            }
        }

        foreach ($billing['tarif'] ?? [] as $grupData) {
            $grup = $jenisTagihan->sasaranGrup()->create(['tipe' => 'tarif', 'nominal' => $grupData['nominal']]);
            foreach ($grupData['kriteria'] as $kriteriaData) {
                $grup->kriteria()->create($kriteriaData);
            }
        }

        foreach ($billing['keringanan'] ?? [] as $ruleData) {
            $jenisTagihan->keringananRules()->create($ruleData);
        }
    }
}
```

- [x] **Step 4: Buat `UpdateJenisTagihanAction` (termasuk dispatch event `BillTypeActivated`, dipindah dari `booted()` model)**

`app/Domains/Keuangan/Actions/JenisTagihan/UpdateJenisTagihanAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\Actions\JenisTagihan;

use App\Domains\Keuangan\DataTransferObjects\JenisTagihanData;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Events\BillTypeActivated;
use Illuminate\Support\Facades\DB;

final class UpdateJenisTagihanAction
{
    public function execute(JenisTagihan $jenisTagihan, JenisTagihanData $data, ?array $billing): JenisTagihan
    {
        DB::transaction(function () use ($jenisTagihan, $data, $billing) {
            if ($billing !== null) {
                app(SyncJenisTagihanBillingConfigAction::class)->execute($jenisTagihan, $billing);
            } else {
                $jenisTagihan->sasaranGrup()->delete();
                $jenisTagihan->keringananRules()->delete();
            }

            $jenisTagihan->update($data->toArray());

            // Dipindah persis dari JenisTagihan::booted() (model tidak boleh punya business
            // logic) — hanya 1 call site nyata yang bisa memicu event ini (update() generik),
            // store()/create() tidak pernah memicu wasChanged('is_active') karena itu bukan
            // "update". Perilaku IDENTIK dengan sebelum migrasi.
            if ($jenisTagihan->wasChanged('is_active') && $jenisTagihan->is_active) {
                event(new BillTypeActivated($jenisTagihan));
            }
        });

        return $jenisTagihan;
    }
}
```

- [x] **Step 5: Buat `DeleteJenisTagihanAction`**

`app/Domains/Keuangan/Actions/JenisTagihan/DeleteJenisTagihanAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\Actions\JenisTagihan;

use App\Domains\Keuangan\Models\JenisTagihan;
use Illuminate\Validation\ValidationException;

final class DeleteJenisTagihanAction
{
    public function execute(JenisTagihan $jenisTagihan): void
    {
        $jumlahTagihan = $jenisTagihan->tagihanItem()->count();
        if ($jumlahTagihan > 0) {
            throw ValidationException::withMessages([
                'jenis_tagihan' => "Tidak bisa dihapus, sudah dipakai di {$jumlahTagihan} tagihan milik calon murid.",
            ]);
        }

        $jumlahNominal = $jenisTagihan->nominalJalur()->count();
        if ($jumlahNominal > 0) {
            throw ValidationException::withMessages([
                'jenis_tagihan' => "Tidak bisa dihapus, sudah ada {$jumlahNominal} nominal jalur yang dikonfigurasi. Hapus dulu di halaman Kelola Nominal.",
            ]);
        }

        $jenisTagihan->delete();
    }
}
```

- [x] **Step 6: Buat `ProsesJenisTagihanBillingAction`**

`app/Domains/Keuangan/Actions/JenisTagihan/ProsesJenisTagihanBillingAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\Actions\JenisTagihan;

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Services\JenisTagihanSasaranMatcher;
use App\Services\TagihanBillingGenerator;

final class ProsesJenisTagihanBillingAction
{
    public function __construct(
        private readonly JenisTagihanSasaranMatcher $matcher,
        private readonly TagihanBillingGenerator $generator,
    ) {}

    /**
     * @return array{bills_generated: int, sudah_tertagih: int, tidak_memenuhi_kriteria: int, gagal: int, status_text: string}
     */
    public function execute(JenisTagihan $jenisTagihan): array
    {
        $totalPool = $this->matcher->countTotalSiswaPool($jenisTagihan);
        $targetCount = $this->matcher->resolveTargetSiswa($jenisTagihan)->count();

        $log = $this->generator->generate($jenisTagihan, 'manual');

        $gagal = count($log->error_log ?? []);
        $tidakMemenuhiKriteria = $totalPool - $targetCount;
        $sudahTertagih = $targetCount - $log->bills_generated - $gagal;

        return [
            'bills_generated' => $log->bills_generated,
            'sudah_tertagih' => $sudahTertagih,
            'tidak_memenuhi_kriteria' => $tidakMemenuhiKriteria,
            'gagal' => $gagal,
            'status_text' => match ($log->status) {
                'success' => 'Berhasil',
                'partial' => 'Selesai Parsial',
                'failed' => 'Gagal Total',
                default => 'Selesai',
            },
        ];
    }
}
```

- [x] **Step 7: Buat `SimpanNominalJenisTagihanAction`**

`app/Domains/Keuangan/Actions/JenisTagihan/SimpanNominalJenisTagihanAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\Actions\JenisTagihan;

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\NominalTagihanJalur;
use App\Models\JalurPpdb;

final class SimpanNominalJenisTagihanAction
{
    public function execute(JenisTagihan $jenisTagihan, array $nominalPerJalur): void
    {
        $jalurIds = JalurPpdb::where('lembaga_id', $jenisTagihan->lembaga_id)->pluck('id');

        foreach ($nominalPerJalur as $jalurPpdbId => $nominal) {
            if (! $jalurIds->contains((int) $jalurPpdbId) || $nominal === null || $nominal === '') {
                continue;
            }

            NominalTagihanJalur::updateOrCreate(
                ['jenis_tagihan_id' => $jenisTagihan->id, 'jalur_ppdb_id' => $jalurPpdbId],
                ['nominal' => $nominal]
            );
        }
    }
}
```

- [x] **Step 8: Commit (belum ada test — Action ini belum dikonsumsi controller sampai Task 12, test scoped baru masuk akal setelah controller jadi)**

```bash
git add -A
git commit -m "feat(keuangan): buat DTO + 6 Action dari business logic JenisTagihanController"
```

---

## Task 11: Fix Sisa Gotcha Referensi Implisit + Cross-Reference

**Files:**
- Verifikasi ulang: `app/Models/BillingJobLog.php`, `app/Models/Tagihan.php`, `app/Models/KategoriKeringanan.php`, `app/Models/Cicilan.php` (sudah diperbaiki di Task 1/4/8 masing-masing — task ini murni verifikasi gabungan, BUKAN mengulang edit)

**Interfaces:**
- Tidak ada file baru — task ini murni verifikasi.

- [x] **Step 1: Verifikasi gabungan seluruh 8 model — tidak ada `use App\Models\{X}` tersisa**

```bash
grep -rln "use App\\\\Models\\\\JenisTagihan;\|use App\\\\Models\\\\NominalTagihanJalur;\|use App\\\\Models\\\\NominalTagihanSiswa;\|use App\\\\Models\\\\JenisTagihanKeringanan;\|use App\\\\Models\\\\JenisTagihanSasaranGrup;\|use App\\\\Models\\\\JenisTagihanSasaranKriteria;\|use App\\\\Models\\\\TagihanItem;\|use App\\\\Models\\\\SkemaCicilan;" --include="*.php" app database tests
```
Expected: KOSONG total.

- [x] **Step 2: Verifikasi gabungan — tidak ada referensi implisit `X::class` tersisa di `app/Models/`**

```bash
grep -rn "JenisTagihan::class\|NominalTagihanJalur::class\|NominalTagihanSiswa::class\|JenisTagihanKeringanan::class\|JenisTagihanSasaranGrup::class\|JenisTagihanSasaranKriteria::class\|TagihanItem::class\|SkemaCicilan::class" --include="*.php" app/Models
```
Expected: KOSONG total (semua sudah FQCN di Task 1/4/8, atau sudah sama-namespace jadi tidak perlu grep-catch).

- [x] **Step 3: Verifikasi 8 file model lama sudah tidak ada di lokasi lama**

```bash
ls app/Models/JenisTagihan.php app/Models/NominalTagihanJalur.php app/Models/NominalTagihanSiswa.php app/Models/JenisTagihanKeringanan.php app/Models/JenisTagihanSasaranGrup.php app/Models/JenisTagihanSasaranKriteria.php app/Models/TagihanItem.php app/Models/SkemaCicilan.php 2>&1
```
Expected: error "No such file or directory" untuk ke-8-nya.

- [x] **Step 4: Kalau ada temuan yang tidak sesuai Step 1-3, STOP dan perbaiki sebelum lanjut Task 12.**

Tidak ada commit di task ini — murni gate verifikasi sebelum masuk ke controller.

---

## Task 12: Refactor `JenisTagihanController` — Namespace + View + Pakai Action

**Files:**
- Create: `app/Http/Controllers/Lembaga/Keuangan/JenisTagihanController.php`
- Delete: `app/Http/Controllers/Admin/JenisTagihanController.php`
- Move: `resources/views/admin/jenis-tagihan/{index,_daftar,_modal-kategori-baru,form,nominal}.blade.php` → `resources/views/portals/lembaga/keuangan/jenis-tagihan/{index,_daftar,_modal-kategori-baru,form,nominal}.blade.php`
- Modify: `routes/admin/keuangan.php`
- Test: `tests/Feature/Admin/JenisTagihan*.php` (sudah ada, TIDAK diubah isinya kecuali ada `assertViewIs` yang perlu path baru — cek dulu dengan grep sebelum asumsi tidak ada)

**Interfaces:**
- Consumes: semua Action & DTO dari Task 10, model dari Task 1-2.

Isi controller SAAT INI (baseline, 445 baris — SUDAH dikutip lengkap di eksplorasi/plan-writing sebelumnya, baca ulang `git show ed25f74:app/Http/Controllers/Admin/JenisTagihanController.php` untuk konfirmasi persis sebelum edit). Kalau isi file yang kamu baca BEDA dari itu, STOP dan laporkan ke user.

- [x] **Step 1: Cek dulu apakah ada `assertViewIs` untuk view jenis-tagihan di test manapun**

```bash
grep -rn "assertViewIs('admin\.jenis-tagihan" tests
```

Catat hasilnya — kalau ADA, file & baris itu WAJIB diupdate di Step 8 nanti (ganti ke `portals.lembaga.keuangan.jenis-tagihan.*`). Kalau KOSONG, tidak ada yang perlu diupdate untuk ini.

- [x] **Step 2: Buat controller baru di namespace `Lembaga\Keuangan\`**

Buat `app/Http/Controllers/Lembaga/Keuangan/JenisTagihanController.php`:

```php
<?php

namespace App\Http\Controllers\Lembaga\Keuangan;

use App\Domains\Keuangan\Actions\JenisTagihan\CreateJenisTagihanAction;
use App\Domains\Keuangan\Actions\JenisTagihan\DeleteJenisTagihanAction;
use App\Domains\Keuangan\Actions\JenisTagihan\ProsesJenisTagihanBillingAction;
use App\Domains\Keuangan\Actions\JenisTagihan\SimpanNominalJenisTagihanAction;
use App\Domains\Keuangan\Actions\JenisTagihan\UpdateJenisTagihanAction;
use App\Domains\Keuangan\DataTransferObjects\JenisTagihanData;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\NominalTagihanJalur;
use App\Models\JalurPpdb;
use App\Models\KategoriKeringanan;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class JenisTagihanController extends BaseController
{
    use AuthorizesRequests;

    private const PPDB_KATEGORI = ['pendaftaran', 'daftar_ulang'];

    private const KRITERIA_FIELDS = ['lembaga', 'tahun_ajaran', 'tingkat', 'kelas', 'jenis_kelamin', 'status_siswa'];

    public function index(Request $request): View
    {
        $this->authorize('jenis-tagihan.view');

        $perPage = in_array((int) $request->input('per_page'), [10, 20, 25, 50]) ? (int) $request->input('per_page') : 20;

        $query = JenisTagihan::withCount(['nominalJalur', 'tagihanItem'])->orderBy('nama');

        if ($search = $request->input('search')) {
            $query->where('nama', 'like', '%' . $search . '%');
        }

        if ($kategori = $request->input('kategori')) {
            $query->where('kategori', $kategori);
        }

        if ($request->has('status') && $request->input('status') !== '') {
            $query->where('is_active', $request->input('status'));
        }

        $paginated = $query->paginate($perPage)->withQueryString();

        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('portals.lembaga.keuangan.jenis-tagihan._daftar', [
                'jenisTagihanList' => $paginated,
                'perPage'          => $perPage,
            ]);
        }

        return view('portals.lembaga.keuangan.jenis-tagihan.index', [
            'jenisTagihanList' => $paginated,
            'perPage'          => $perPage,
            'totalJenis'       => JenisTagihan::count(),
            'totalAktif'       => JenisTagihan::where('is_active', true)->count(),
            'totalDipakai'     => JenisTagihan::has('tagihanItem')->count(),
            'kategoriList'     => [
                'pendaftaran'  => 'Pendaftaran',
                'daftar_ulang' => 'Daftar Ulang',
                'spp'          => 'SPP',
                'tahunan'      => 'Tahunan',
                'kegiatan'     => 'Kegiatan',
                'lainnya'      => 'Lainnya',
                'custom'       => 'Custom',
            ],
            'statusList'       => [
                '1' => 'Aktif',
                '0' => 'Tidak Aktif',
            ],
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $this->authorize('jenis-tagihan.create');

        $lembagaId = $this->resolveLembagaIdOrFail($request);
        if ($lembagaId === null) {
            return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah jenis tagihan.']);
        }

        return view('portals.lembaga.keuangan.jenis-tagihan.form', array_merge(
            ['jenisTagihan' => null],
            $this->referenceData($lembagaId)
        ));
    }

    public function edit(JenisTagihan $jenisTagihan): View
    {
        $this->authorize('jenis-tagihan.edit');

        $jenisTagihan->load(['sasaranGrup.kriteria', 'keringananRules.kategoriKeringanan']);

        return view('portals.lembaga.keuangan.jenis-tagihan.form', array_merge(
            ['jenisTagihan' => $jenisTagihan],
            $this->referenceData($jenisTagihan->lembaga_id)
        ));
    }

    public function store(Request $request, CreateJenisTagihanAction $action): RedirectResponse|JsonResponse
    {
        $this->authorize('jenis-tagihan.create');

        $lembagaId = $this->resolveLembagaIdOrFail($request);
        if ($lembagaId === null) {
            $message = 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah jenis tagihan.';

            if ($request->wantsJson()) {
                return response()->json(['message' => $message, 'errors' => ['lembaga_id' => [$message]]], 422);
            }

            return back()->withErrors(['lembaga_id' => $message])->withInput();
        }

        $isPpdbKategori = in_array($request->input('kategori'), self::PPDB_KATEGORI, true);

        if ($isPpdbKategori && $this->hasBillingPayload($request)) {
            return $this->errorResponse($request, 'Target sasaran, tarif berdimensi, dan keringanan hanya berlaku untuk kategori selain Pendaftaran/Daftar Ulang.');
        }

        $data = $request->validate($this->baseRules($lembagaId, null));
        $data['bisa_dicicil'] = $request->boolean('bisa_dicicil');
        $data['is_active'] = $request->boolean('is_active');

        $billing = null;
        if (! $isPpdbKategori) {
            $billing = $request->validate($this->billingRules($lembagaId, $request));
            $duplicateError = $this->findDuplicateKeringanan($billing['keringanan'] ?? []);
            if ($duplicateError) {
                return $this->errorResponse($request, $duplicateError);
            }
        }

        if ($request->user()->widestScopeLevel() === 'yayasan') {
            $data['lembaga_id'] = $lembagaId;
        }

        $jenisTagihan = $action->execute(JenisTagihanData::fromArray($data), $billing);

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $jenisTagihan->fresh(),
                'redirect' => $isPpdbKategori ? route('admin.jenis-tagihan.nominal', $jenisTagihan) : null,
            ], 201);
        }

        if ($isPpdbKategori) {
            return redirect()->route('admin.jenis-tagihan.nominal', $jenisTagihan)
                ->with('status', 'Jenis tagihan berhasil ditambahkan. Atur nominal per jalur di bawah.');
        }

        return redirect()->route('admin.jenis-tagihan.index')
            ->with('status', 'Jenis tagihan berhasil ditambahkan.');
    }

    public function update(Request $request, JenisTagihan $jenisTagihan, UpdateJenisTagihanAction $action): RedirectResponse|JsonResponse
    {
        $this->authorize('jenis-tagihan.edit');

        $isPpdbKategori = in_array($request->input('kategori'), self::PPDB_KATEGORI, true);

        if ($isPpdbKategori && $this->hasBillingPayload($request)) {
            return $this->errorResponse($request, 'Target sasaran, tarif berdimensi, dan keringanan hanya berlaku untuk kategori selain Pendaftaran/Daftar Ulang.');
        }

        $data = $request->validate($this->baseRules($jenisTagihan->lembaga_id, $jenisTagihan));
        $data['bisa_dicicil'] = $request->boolean('bisa_dicicil');
        $data['is_active'] = $request->boolean('is_active');

        $billing = null;
        if (! $isPpdbKategori) {
            $billing = $request->validate($this->billingRules($jenisTagihan->lembaga_id, $request));
            $duplicateError = $this->findDuplicateKeringanan($billing['keringanan'] ?? []);
            if ($duplicateError) {
                return $this->errorResponse($request, $duplicateError);
            }
        }

        $jenisTagihan = $action->execute($jenisTagihan, JenisTagihanData::fromArray($data), $billing);

        if ($request->wantsJson()) {
            return response()->json(['data' => $jenisTagihan->fresh()->loadCount(['nominalJalur', 'tagihanItem'])]);
        }

        return redirect()->route('admin.jenis-tagihan.index')->with('status', 'Jenis tagihan berhasil diperbarui.');
    }

    public function destroy(Request $request, JenisTagihan $jenisTagihan, DeleteJenisTagihanAction $action): RedirectResponse|JsonResponse
    {
        $this->authorize('jenis-tagihan.delete');

        try {
            $action->execute($jenisTagihan);
        } catch (ValidationException $exception) {
            return $this->errorResponse($request, $exception->errors()['jenis_tagihan'][0]);
        }

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Jenis tagihan berhasil dihapus.']);
        }

        return redirect()->route('admin.jenis-tagihan.index')->with('status', 'Jenis tagihan berhasil dihapus.');
    }

    public function prosesTagihan(JenisTagihan $jenisTagihan, ProsesJenisTagihanBillingAction $action): JsonResponse
    {
        $this->authorize('jenis-tagihan.edit');

        if (in_array($jenisTagihan->kategori, self::PPDB_KATEGORI, true)) {
            return response()->json([
                'message' => "Jenis tagihan berkategori {$jenisTagihan->kategori} tidak bisa diproses lewat billing engine — gunakan alur pendaftaran PPDB.",
            ], 422);
        }

        $hasil = $action->execute($jenisTagihan);

        return response()->json(array_merge($hasil, [
            'message' => "{$hasil['bills_generated']} tagihan dibuat, {$hasil['sudah_tertagih']} sudah tertagih, {$hasil['tidak_memenuhi_kriteria']} tidak memenuhi kriteria, {$hasil['gagal']} gagal.",
        ]));
    }

    public function nominal(JenisTagihan $jenisTagihan): View|RedirectResponse
    {
        $this->authorize('jenis-tagihan.edit');

        if (! in_array($jenisTagihan->kategori, self::PPDB_KATEGORI, true)) {
            return redirect()->route('admin.jenis-tagihan.index')
                ->withErrors(['kategori' => 'Nominal per jalur PPDB hanya berlaku untuk kategori Pendaftaran/Daftar Ulang. Kategori "Lainnya" belum punya mekanisme penentuan nominal.']);
        }

        $tahunAjaranAktif = TahunAjaran::where('lembaga_id', $jenisTagihan->lembaga_id)->where('status_aktif', true)->first();

        return view('portals.lembaga.keuangan.jenis-tagihan.nominal', [
            'jenisTagihan' => $jenisTagihan,
            'jalurList' => $tahunAjaranAktif
                ? JalurPpdb::where('tahun_ajaran_id', $tahunAjaranAktif->id)->orderBy('nama')->get()
                : collect(),
            'nominalMap' => NominalTagihanJalur::where('jenis_tagihan_id', $jenisTagihan->id)->pluck('nominal', 'jalur_ppdb_id'),
            'tahunAjaranAktif' => $tahunAjaranAktif,
        ]);
    }

    public function simpanNominal(Request $request, JenisTagihan $jenisTagihan, SimpanNominalJenisTagihanAction $action): RedirectResponse
    {
        $this->authorize('jenis-tagihan.edit');

        if (! in_array($jenisTagihan->kategori, self::PPDB_KATEGORI, true)) {
            return redirect()->route('admin.jenis-tagihan.index')
                ->withErrors(['kategori' => 'Nominal per jalur PPDB hanya berlaku untuk kategori Pendaftaran/Daftar Ulang.']);
        }

        $data = $request->validate([
            'nominal' => ['required', 'array'],
            'nominal.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $action->execute($jenisTagihan, $data['nominal']);

        return redirect()->route('admin.jenis-tagihan.nominal', $jenisTagihan)->with('status', 'Nominal berhasil disimpan.');
    }

    private function resolveLembagaIdOrFail(Request $request): ?int
    {
        if ($request->user()->widestScopeLevel() === 'yayasan') {
            return session('active_lembaga_id');
        }

        return $request->user()->lembaga_id;
    }

    private function referenceData(int $lembagaId): array
    {
        return [
            'lembagaList' => Lembaga::orderBy('nama')->get(['id', 'nama']),
            'tahunAjaranList' => TahunAjaran::where('lembaga_id', $lembagaId)->orderBy('nama')->get(['id', 'nama']),
            'kelasList' => Kelas::where('lembaga_id', $lembagaId)
                ->with(['tahunAjaran' => fn ($q) => $q->withoutGlobalScope(\App\Models\Scopes\TenantScope::class)])
                ->orderBy('nama')->get(['id', 'nama', 'tahun_ajaran_id']),
            'tingkatList' => Kelas::where('lembaga_id', $lembagaId)->whereNotNull('tingkat')->distinct()->orderBy('tingkat')->pluck('tingkat'),
            'kategoriKeringananList' => KategoriKeringanan::where('lembaga_id', $lembagaId)->orderBy('nama')->get(['id', 'nama']),
        ];
    }

    private function hasBillingPayload(Request $request): bool
    {
        return $request->has('sasaran') || $request->has('tarif') || $request->has('keringanan');
    }

    private function baseRules(int $lembagaId, ?JenisTagihan $editing): array
    {
        return [
            'nama' => ['required', 'string', 'max:255', Rule::unique('jenis_tagihan', 'nama')
                ->where(fn ($query) => $query->where('lembaga_id', $lembagaId))
                ->ignore($editing?->id)],
            'kategori' => ['required', Rule::in(['pendaftaran', 'daftar_ulang', 'lainnya', 'spp', 'tahunan', 'kegiatan', 'custom'])],
            'bisa_dicicil' => ['nullable', 'boolean'],
            'maks_cicilan' => ['nullable', 'integer', 'min:2', 'required_if:bisa_dicicil,1'],
            'default_amount' => ['nullable', 'numeric', 'min:0'],
            'mode' => ['nullable', Rule::in(['manual', 'otomatis'])],
            'tanggal_mulai' => ['nullable', 'date', 'required_if:mode,otomatis'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'tanggal_generate' => ['nullable', 'integer', 'between:1,31', 'required_if:mode,otomatis'],
            'hari_jatuh_tempo' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    private function billingRules(int $lembagaId, Request $request): array
    {
        return [
            'sasaran' => ['nullable', 'array'],
            'sasaran.*.kriteria' => ['required', 'array', 'min:1'],
            'sasaran.*.kriteria.*.field' => ['required', Rule::in(self::KRITERIA_FIELDS)],
            'sasaran.*.kriteria.*.operator' => ['required', Rule::in(['in', 'not_in'])],
            'sasaran.*.kriteria.*.value' => ['required', 'array', 'min:1'],
            'sasaran.*.kriteria.*.value.*' => ['string', 'max:255'],
            'tarif' => ['nullable', 'array'],
            'tarif.*.nominal' => ['required', 'numeric', 'min:0'],
            'tarif.*.kriteria' => ['required', 'array', 'min:1'],
            'tarif.*.kriteria.*.field' => ['required', Rule::in(self::KRITERIA_FIELDS)],
            'tarif.*.kriteria.*.operator' => ['required', Rule::in(['in', 'not_in'])],
            'tarif.*.kriteria.*.value' => ['required', 'array', 'min:1'],
            'tarif.*.kriteria.*.value.*' => ['string', 'max:255'],
            'keringanan' => ['nullable', 'array'],
            'keringanan.*.kategori_keringanan_id' => ['required', 'integer', Rule::exists('kategori_keringanan', 'id')->where('lembaga_id', $lembagaId)],
            'keringanan.*.tipe_potongan' => ['required', Rule::in(['fixed', 'persen'])],
            'keringanan.*.nilai' => ['required', 'numeric', 'min:0', function ($attribute, $value, $fail) use ($request) {
                preg_match('/keringanan\.(\d+)\.nilai/', $attribute, $matches);
                $index = $matches[1] ?? null;
                $tipe = $request->input("keringanan.{$index}.tipe_potongan");
                if ($tipe === 'persen' && $value > 100) {
                    $fail('Potongan persentase tidak boleh lebih dari 100.');
                }
            }],
            'keringanan.*.keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function findDuplicateKeringanan(array $keringanan): ?string
    {
        $ids = array_column($keringanan, 'kategori_keringanan_id');
        if (count($ids) !== count(array_unique($ids))) {
            return 'Satu kategori keringanan tidak boleh dipakai lebih dari sekali untuk jenis tagihan yang sama.';
        }

        return null;
    }

    private function errorResponse(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return back()->withErrors(['jenis_tagihan' => $message])->withInput();
    }
}
```

**Catatan perbedaan dari controller lama (SEMUA disengaja, bukan kelalaian):**
- `syncBillingConfig()` dan `resolveLembagaIdOrFail()`'s pemakaian di `store()`/`update()` sekarang lewat Action (`CreateJenisTagihanAction`/`UpdateJenisTagihanAction` yang di dalamnya panggil `SyncJenisTagihanBillingConfigAction`).
- `destroy()` sekarang `try/catch(ValidationException)` alih-alih 2 `if` manual — HASIL AKHIR (pesan, status code, redirect/JSON) identik, cuma jalur kode di controller yang berbeda karena logic count-check pindah ke `DeleteJenisTagihanAction`.
- `prosesTagihan()` tidak lagi inject `JenisTagihanSasaranMatcher`/`TagihanBillingGenerator` langsung — sekarang lewat `ProsesJenisTagihanBillingAction` yang membungkus keduanya. Response JSON body IDENTIK (field yang sama, urutan hitung yang sama).
- `simpanNominal()` — loop `foreach` pindah ke `SimpanNominalJenisTagihanAction`, controller cuma validasi lalu panggil Action.
- `resolveLembagaIdOrFail()`, `referenceData()`, `hasBillingPayload()`, `baseRules()`, `billingRules()`, `findDuplicateKeringanan()`, `errorResponse()` — TETAP private method di controller (bukan Action) karena murni terkait HTTP request-parsing/reference-data-untuk-view, bukan business logic domain inti. Ini konsisten dengan preseden (`MataPelajaranController` di Data Induk Sempit juga menyisakan beberapa private helper HTTP-layer di controller).

- [x] **Step 3: Hapus controller lama**

```bash
git rm app/Http/Controllers/Admin/JenisTagihanController.php
```

- [x] **Step 4: Pindahkan 5 view**

```bash
mkdir -p resources/views/portals/lembaga/keuangan/jenis-tagihan
git mv resources/views/admin/jenis-tagihan/index.blade.php resources/views/portals/lembaga/keuangan/jenis-tagihan/index.blade.php
git mv resources/views/admin/jenis-tagihan/_daftar.blade.php resources/views/portals/lembaga/keuangan/jenis-tagihan/_daftar.blade.php
git mv resources/views/admin/jenis-tagihan/_modal-kategori-baru.blade.php resources/views/portals/lembaga/keuangan/jenis-tagihan/_modal-kategori-baru.blade.php
git mv resources/views/admin/jenis-tagihan/form.blade.php resources/views/portals/lembaga/keuangan/jenis-tagihan/form.blade.php
git mv resources/views/admin/jenis-tagihan/nominal.blade.php resources/views/portals/lembaga/keuangan/jenis-tagihan/nominal.blade.php
```

- [x] **Step 5: Perbaiki 2 `@include` yang sudah diketahui (JANGAN blanket sed, edit manual persis 2 baris ini)**

Di `resources/views/portals/lembaga/keuangan/jenis-tagihan/form.blade.php`, cari baris:
```blade
        @include('admin.jenis-tagihan._modal-kategori-baru')
```
Ganti jadi:
```blade
        @include('portals.lembaga.keuangan.jenis-tagihan._modal-kategori-baru')
```

Di `resources/views/portals/lembaga/keuangan/jenis-tagihan/index.blade.php`, cari baris:
```blade
                @include('admin.jenis-tagihan._daftar')
```
Ganti jadi:
```blade
                @include('portals.lembaga.keuangan.jenis-tagihan._daftar')
```

Baris `route('admin.jenis-tagihan...')` di dalam ke-5 view TIDAK diubah — nama route tetap sama.

- [x] **Step 6: Update `routes/admin/keuangan.php`**

Ganti baris:
```php
use App\Http\Controllers\Admin\JenisTagihanController;
```
menjadi:
```php
use App\Http\Controllers\Lembaga\Keuangan\JenisTagihanController;
```

Seluruh baris route `jenis-tagihan.*` (baris 12-20 di baseline) TIDAK diubah.

- [x] **Step 7: Verifikasi tidak ada view/route yang salah path**

```bash
grep -rn "route('portals\." resources/views/portals
```
Expected: kosong.

```bash
php artisan route:list --name=jenis-tagihan
```
Expected: 9 route (`create, index, store, edit, update, destroy, proses, nominal, nominal.store`), nama SAMA seperti sebelumnya (prefix `admin.jenis-tagihan.*`), Action mengarah ke `Lembaga\Keuangan\JenisTagihanController`.

- [x] **Step 8: Update `assertViewIs` kalau ditemukan di Step 1 (kondisional)**

Kalau Step 1 menemukan hasil grep tidak kosong, edit baris itu: ganti string view lama (`admin.jenis-tagihan.*`) jadi `portals.lembaga.keuangan.jenis-tagihan.*` yang sesuai. Kalau Step 1 kosong, skip step ini.

- [x] **Step 9: Jalankan test scoped**

```bash
grep -rln "JenisTagihan" tests/Feature/Admin --include="*.php" -l
```

Jalankan SEMUA file hasil grep di atas (nama file pasti bisa beda dari daftar di spec karena waktu berlalu — pakai hasil grep nyata ini):

```bash
php artisan test <daftar file hasil grep di atas, dipisah spasi>
```
Expected: semua PASS, 0 failed, 0 error.

- [x] **Step 10: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): refactor JenisTagihanController jadi Action/DTO, pindah ke Lembaga\Keuangan\, view ke portals/lembaga/keuangan/"
```

---

## Task 13: Verifikasi Akhir + Handoff Log

**Files:**
- Create: `.agents/logs/2026-08-24-refactor-02-keuangan-sp1-konfigurasi-tagihan.md`

- [x] **Step 1: Jalankan test scoped gabungan luas (seluruh area Keuangan yang tersentuh)**

```bash
php artisan test tests/Feature/Keuangan tests/Feature/Admin tests/Unit tests/Feature/Spmb tests/Feature/Portal
```

Catat jumlah pasti passed/failed. **Flaky yang sudah dikenal**: test yang memakai `now()` untuk cek hari libur mingguan SDM (`ScanQrAttendanceActionTest`, `AttendanceControllerTest`) bisa gagal kalau kebetulan dijalankan hari Minggu — kalau itu SATU-SATUNYA yang gagal, jalankan ulang sendirian untuk konfirmasi, BUKAN regresi dari sub-project ini.

- [x] **Step 2: Verifikasi gabungan final (ulangi Task 11 sekali lagi, pastikan Task 12 tidak menambah referensi lama baru)**

```bash
grep -rln "use App\\\\Models\\\\JenisTagihan;\|use App\\\\Models\\\\NominalTagihanJalur;\|use App\\\\Models\\\\NominalTagihanSiswa;\|use App\\\\Models\\\\JenisTagihanKeringanan;\|use App\\\\Models\\\\JenisTagihanSasaranGrup;\|use App\\\\Models\\\\JenisTagihanSasaranKriteria;\|use App\\\\Models\\\\TagihanItem;\|use App\\\\Models\\\\SkemaCicilan;\|use App\\\\Services\\\\JenisTagihanSasaranMatcher;\|use App\\\\Services\\\\TagihanNominalResolver;" --include="*.php" app database tests
```
Expected: KOSONG total.

- [x] **Step 3: Minta izin user untuk full test suite**

Tanya ke user: "Task 1-12 selesai, test scoped semua hijau. Boleh saya jalankan full test suite (`php artisan test`) untuk verifikasi akhir?" — TUNGGU jawaban eksplisit. JANGAN jalankan otomatis tanpa izin.

- [x] **Step 4: Jalankan full suite (HANYA setelah izin didapat)**

```bash
php artisan test
```

Catat angka PASTI passed/failed/duration.

- [x] **Step 5: Tulis handoff log**

Buat `.agents/logs/2026-08-24-refactor-02-keuangan-sp1-konfigurasi-tagihan.md` (Bahasa Indonesia): ringkasan tiap task (1-12) dengan commit hash, hasil test dengan angka PASTI dari Step 1 dan Step 4 (JANGAN dicampur/disatukan), hasil Step 2 (harus "kosong"). Sebutkan eksplisit kalau ada file di luar daftar yang disebutkan plan yang ternyata perlu disentuh (jangan diam-diam seperti insiden Data Induk Sempit sebelumnya) — laporkan sebagai temuan terpisah di log, bukan disembunyikan di 1 baris netral tabel commit.

- [x] **Step 6: Update `.agents/plans/2026-08-20-1800-master-refactor-domain-pattern.md` §6**

Tambahkan baris baru di tabel Sub-Task untuk "Migrasi Domain Keuangan Sub-project 1 (Konfigurasi & Generasi Tagihan)" dengan link ke spec/plan/log, status 🟢 SELESAI.

- [x] **Step 7: Commit**

```bash
git add .agents/logs/2026-08-24-refactor-02-keuangan-sp1-konfigurasi-tagihan.md .agents/plans/2026-08-20-1800-master-refactor-domain-pattern.md
git commit -m "docs(refactor): handoff log migrasi domain Keuangan Sub-project 1"
```
