# Migrasi Domain Keuangan Sub-project 5 (Mini, Penutup Celah): Kategori & Siswa Keringanan Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Memindahkan `KategoriKeringanan`, `SiswaKeringanan`, dan `KategoriKeringananController` ke `app/Domains/Keuangan/*` — menutup celah kecil yang lolos dari audit final SP4.

**Architecture:** Zero-behavior-change murni. 2 model + 1 controller pindah, tidak ada Action/DTO baru, tidak ada restrukturisasi. Route name/path tidak berubah.

**Tech Stack:** Laravel 12, Pest.

## Global Constraints

- **Zero-behavior-change total** — tidak ada pengecualian di sub-project ini (beda dengan SP4 yang punya 1 bug fix disengaja).
- Route name `admin.kategori-keringanan.store` dan path TIDAK berubah — hanya controller FQCN yang dirujuk di `routes/admin/keuangan.php` yang berubah.
- File test TIDAK dipindah foldernya — hanya `use` import diupdate.
- Baseline kode: commit `032200b` di branch `refactor-v1`. Kalau isi file yang dibaca BEDA signifikan dari yang dikutip plan, STOP, laporkan ke user.
- Verifikasi grep WAJIB scope `app database tests`, pola `App\Models\{ClassName}\b` (menangkap `use` DAN FQCN inline).
- Test scoped SEBELUM commit. Full suite HANYA task terakhir, izin eksplisit user dulu.

---

## Task 1: Pindahkan Model `KategoriKeringanan` dan `SiswaKeringanan` (Bersamaan)

**Files:**
- Move: `app/Models/KategoriKeringanan.php` → `app/Domains/Keuangan/Models/KategoriKeringanan.php`
- Move: `app/Models/SiswaKeringanan.php` → `app/Domains/Keuangan/Models/SiswaKeringanan.php`
- Modify: `app/Domains/Keuangan/Models/JenisTagihanKeringanan.php` (hapus `use` redundan, sederhanakan FQCN)
- Modify: `app/Domains/Keuangan/Services/TagihanNominalResolver.php` (update `use`)
- Modify: `app/Http/Controllers/Lembaga/Keuangan/JenisTagihanController.php` (update `use`)

**Interfaces:**
- Produces: `App\Domains\Keuangan\Models\KategoriKeringanan`, `App\Domains\Keuangan\Models\SiswaKeringanan` — dipakai Task 2 (controller) dan konsumer existing.

Kedua model TIDAK punya relasi implisit ke class lain yang tetap tinggal di luar domain (bedanya dengan gotcha dua arah SP4) — `KategoriKeringanan::lembaga()` mengarah ke `App\Models\Lembaga` yang memang generic dan tetap `use` biasa.

- [ ] **Step 1: Baca ulang kedua file existing untuk konfirmasi isi sama persis dengan baseline**

```bash
cat app/Models/KategoriKeringanan.php
cat app/Models/SiswaKeringanan.php
```

Bandingkan dengan kutipan di Step 2/3 di bawah — kalau berbeda signifikan, STOP dan laporkan.

- [ ] **Step 2: Pindahkan `KategoriKeringanan.php` dan timpa isinya**

```bash
git mv app/Models/KategoriKeringanan.php app/Domains/Keuangan/Models/KategoriKeringanan.php
```

Timpa seluruh isi `app/Domains/Keuangan/Models/KategoriKeringanan.php` dengan:

```php
<?php
// app/Domains/Keuangan/Models/KategoriKeringanan.php

namespace App\Domains\Keuangan\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Lembaga;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KategoriKeringanan extends Model
{
    use BelongsToTenant;

    protected $table = 'kategori_keringanan';

    protected $fillable = ['lembaga_id', 'nama', 'keterangan'];

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function jenisTagihanKeringanan(): HasMany
    {
        return $this->hasMany(JenisTagihanKeringanan::class);
    }

    public function siswaKeringanan(): HasMany
    {
        return $this->hasMany(SiswaKeringanan::class);
    }
}
```

Catatan: `JenisTagihanKeringanan::class` sekarang bare (bukan FQCN) karena sama-namespace `Domains\Keuangan\Models`. `SiswaKeringanan::class` juga bare, sah karena dipindah bersamaan di task ini. `Lembaga::class` pakai `use App\Models\Lembaga;` biasa karena `Lembaga` tetap generic, tidak pindah.

- [ ] **Step 3: Pindahkan `SiswaKeringanan.php` dan timpa isinya**

```bash
git mv app/Models/SiswaKeringanan.php app/Domains/Keuangan/Models/SiswaKeringanan.php
```

Timpa seluruh isi `app/Domains/Keuangan/Models/SiswaKeringanan.php` dengan:

```php
<?php
// app/Domains/Keuangan/Models/SiswaKeringanan.php

namespace App\Domains\Keuangan\Models;

use App\Models\Siswa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiswaKeringanan extends Model
{
    protected $table = 'siswa_keringanan';

    protected $fillable = ['siswa_id', 'kategori_keringanan_id', 'berlaku_dari', 'berlaku_sampai'];

    protected function casts(): array
    {
        return [
            'berlaku_dari' => 'date',
            'berlaku_sampai' => 'date',
        ];
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function kategoriKeringanan(): BelongsTo
    {
        return $this->belongsTo(KategoriKeringanan::class);
    }
}
```

Catatan: `KategoriKeringanan::class` bare, sah karena sama-namespace setelah Step 2. `Siswa::class` pakai `use App\Models\Siswa;` biasa karena `Siswa` tetap generic, tidak pindah.

- [ ] **Step 4: Perbaiki gotcha dua arah di `app/Domains/Keuangan/Models/JenisTagihanKeringanan.php`**

Baca file, hapus baris `use App\Models\KategoriKeringanan;` (baris 6 baseline) — sekarang redundan karena `KategoriKeringanan` sudah sama-namespace. Isi method `kategoriKeringanan(): BelongsTo { return $this->belongsTo(KategoriKeringanan::class); }` TIDAK berubah (tetap bare, sekarang otomatis benar).

- [ ] **Step 5: Update `use` di `app/Domains/Keuangan/Services/TagihanNominalResolver.php`**

Baca file, ganti baris `use App\Models\SiswaKeringanan;` (baris 10 baseline) menjadi `use App\Domains\Keuangan\Models\SiswaKeringanan;`. Baris pemakaian `SiswaKeringanan::where(...)` di `resolveDiscount()` TIDAK berubah.

- [ ] **Step 6: Update `use` di `app/Http/Controllers/Lembaga/Keuangan/JenisTagihanController.php`**

Baca file, ganti baris `use App\Models\KategoriKeringanan;` (baris 16 baseline) menjadi `use App\Domains\Keuangan\Models\KategoriKeringanan;`. Baris pemakaian `KategoriKeringanan::where(...)` di `referenceData()` TIDAK berubah.

- [ ] **Step 7: Grep ulang untuk daftar consumer lain yang masih pakai namespace lama**

```bash
grep -rln "App\\\\Models\\\\KategoriKeringanan\b" --include="*.php" app database tests
grep -rln "App\\\\Models\\\\SiswaKeringanan\b" --include="*.php" app database tests
```

Daftar consumer test yang WAJIB diupdate (grep ulang untuk konfirmasi, daftar per 24 Agustus 2026):
```
tests/Feature/Admin/JenisTagihanFormTest.php
tests/Feature/Keuangan/TagihanBillingGeneratorTest.php
tests/Feature/Admin/JenisTagihanFinalReviewFixesTest.php
tests/Feature/Keuangan/TagihanNominalResolverTest.php
tests/Feature/Keuangan/KeringananTest.php
tests/Feature/Admin/JenisTagihanKeringananFormTest.php
tests/Feature/Admin/KategoriKeringananTest.php
```

Untuk SETIAP file test di atas: baca, ganti `use App\Models\KategoriKeringanan;` → `use App\Domains\Keuangan\Models\KategoriKeringanan;` dan/atau `use App\Models\SiswaKeringanan;` → `use App\Domains\Keuangan\Models\SiswaKeringanan;` (sesuai yang dipakai file itu — tidak semua file pakai keduanya, cek dulu). JANGAN ubah logic test apapun, HANYA baris `use`.

- [ ] **Step 8: Verifikasi grep final**

```bash
grep -rln "App\\\\Models\\\\KategoriKeringanan\b" --include="*.php" app database tests
grep -rln "App\\\\Models\\\\SiswaKeringanan\b" --include="*.php" app database tests
```
Expected: KEDUANYA kosong (controller `Admin\KategoriKeringananController.php` yang masih pakai `use App\Models\KategoriKeringanan;` akan ditangani Task 2 — kalau plan dieksekusi berurutan Task 1 dulu, controller lama ini masih ada di Task 1 selesai, JANGAN kaget kalau grep di atas masih menunjukkan file itu; Task 2 akan menghapusnya).

- [ ] **Step 9: Jalankan test scoped**

```bash
php artisan test tests/Feature/Keuangan/TagihanNominalResolverTest.php tests/Feature/Keuangan/TagihanBillingGeneratorTest.php tests/Feature/Keuangan/KeringananTest.php
```
Expected: semua PASS.

- [ ] **Step 10: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): pindah model KategoriKeringanan dan SiswaKeringanan ke Domains\Keuangan\Models"
```

---

## Task 2: Pindahkan Controller `KategoriKeringananController` ke `Lembaga\Keuangan`

**Files:**
- Create: `app/Http/Controllers/Lembaga/Keuangan/KategoriKeringananController.php`
- Delete: `app/Http/Controllers/Admin/KategoriKeringananController.php`
- Modify: `routes/admin/keuangan.php`
- Modify: `tests/Feature/Admin/KategoriKeringananTest.php` (update `use` model saja, sudah tercakup Task 1 Step 7 — task ini murni verifikasi route masih hijau)

**Interfaces:**
- Consumes: `App\Domains\Keuangan\Models\KategoriKeringanan` (Task 1).

Controller ini TETAP thin (1 method `store()`, validasi inline) — TIDAK ada Action/DTO baru diekstrak, sesuai keputusan §4.1 spec.

Baseline kode (43 baris, commit `032200b`) — baca ulang untuk konfirmasi sebelum edit.

- [ ] **Step 1: Buat controller baru di `Lembaga\Keuangan\`**

`app/Http/Controllers/Lembaga/Keuangan/KategoriKeringananController.php`:

```php
<?php
// app/Http/Controllers/Lembaga/Keuangan/KategoriKeringananController.php

namespace App\Http\Controllers\Lembaga\Keuangan;

use App\Domains\Keuangan\Models\KategoriKeringanan;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KategoriKeringananController extends Controller
{
    use AuthorizesRequests;

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('jenis-tagihan.create') || $request->user()->can('jenis-tagihan.edit'), 403);

        $lembagaId = $request->user()->widestScopeLevel() === 'yayasan'
            ? session('active_lembaga_id')
            : $request->user()->lembaga_id;

        if ($lembagaId === null) {
            return response()->json([
                'message' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah kategori keringanan.',
                'errors' => ['lembaga_id' => ['Pilih lembaga aktif melalui pengalih lembaga sebelum menambah kategori keringanan.']],
            ], 422);
        }

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255', Rule::unique('kategori_keringanan', 'nama')
                ->where(fn ($query) => $query->where('lembaga_id', $lembagaId))],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ]);
        $data['lembaga_id'] = $lembagaId;

        $kategori = KategoriKeringanan::create($data);

        return response()->json(['data' => $kategori], 201);
    }
}
```

Catatan: base class diganti dari `Illuminate\Routing\Controller as BaseController` (baseline) menjadi `App\Http\Controllers\Controller` (konsisten dengan `Controller` yang dipakai `JenisTagihanController` sibling-nya di namespace `Lembaga\Keuangan` yang sama) — ini konsisten dengan preseden Task 9 SP4 (`DashboardController` juga distandarkan base class-nya saat pindah scope, dikonfirmasi review independen sebagai "standarisasi minor, tidak ada impact"). Logic `store()` method itu sendiri TIDAK berubah sama sekali.

- [ ] **Step 2: Hapus controller lama**

```bash
git rm app/Http/Controllers/Admin/KategoriKeringananController.php
```

- [ ] **Step 3: Update `routes/admin/keuangan.php`**

Baca file, ganti baris `use App\Http\Controllers\Admin\KategoriKeringananController;` (baris 5 baseline) menjadi `use App\Http\Controllers\Lembaga\Keuangan\KategoriKeringananController;`. Baris route itu sendiri (`Route::post('kategori-keringanan', [KategoriKeringananController::class, 'store'])->name('kategori-keringanan.store');`, baris 25 baseline) TIDAK berubah — nama route dan path tetap sama, hanya `use` di atas yang membuatnya menunjuk class baru.

- [ ] **Step 4: Grep verifikasi tidak ada sisa referensi controller lama**

```bash
grep -rln "Controllers\\\\Admin\\\\KategoriKeringananController" --include="*.php" app routes tests
```
Expected: kosong.

```bash
ls app/Http/Controllers/Admin/KategoriKeringananController.php
```
Expected: error "No such file or directory".

- [ ] **Step 5: Konfirmasi route name/path tidak berubah**

```bash
php artisan route:list --name=kategori-keringanan
```
Expected: `admin.kategori-keringanan.store`, method POST, path sama seperti sebelum migrasi, Action menunjuk `Lembaga\Keuangan\KategoriKeringananController@store`.

- [ ] **Step 6: Jalankan test scoped**

```bash
php artisan test tests/Feature/Admin/KategoriKeringananTest.php tests/Feature/Admin/JenisTagihanKeringananFormTest.php tests/Feature/Admin/JenisTagihanFormTest.php tests/Feature/Admin/JenisTagihanFinalReviewFixesTest.php
```
Expected: semua PASS.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "refactor(keuangan): pindah KategoriKeringananController ke Lembaga\Keuangan\, route name/path tidak berubah"
```

---

## Task 3: Verifikasi Menyeluruh + Full Suite + Handoff Log

**Files:**
- Create: `.agents/logs/2026-08-24-refactor-02-keuangan-sp5-kategori-keringanan.md`
- Modify: `.agents/plans/2026-08-20-1800-master-refactor-domain-pattern.md` (§6, baris baru)

- [ ] **Step 1: Grep gabungan final**

```bash
grep -rln "App\\\\Models\\\\KategoriKeringanan\b\|App\\\\Models\\\\SiswaKeringanan\b\|Controllers\\\\Admin\\\\KategoriKeringananController" --include="*.php" app database tests routes
```
Expected: KOSONG total.

- [ ] **Step 2: Verifikasi file lama sudah tidak ada**

```bash
ls app/Models/KategoriKeringanan.php app/Models/SiswaKeringanan.php app/Http/Controllers/Admin/KategoriKeringananController.php 2>&1
```
Expected: error "No such file or directory" untuk ketiganya.

- [ ] **Step 3: Jalankan test scoped luas**

```bash
php artisan test tests/Feature/Keuangan tests/Feature/Admin --filter="Keringanan|JenisTagihan|TagihanBillingGenerator|TagihanNominalResolver"
```
Expected: semua PASS.

- [ ] **Step 4: Minta izin user untuk full test suite**

Tanya ke user: "Task 1-3 selesai, grep gabungan kosong total, test scoped semua hijau. Boleh saya jalankan full test suite untuk verifikasi akhir?" — TUNGGU jawaban eksplisit. JANGAN jalankan otomatis. JANGAN jalankan proses test lain bersamaan dengan full suite ini.

- [ ] **Step 5: Jalankan full suite SOLO**

```bash
php artisan test
```
Catat angka PASTI passed/failed/duration. Kalau ada kegagalan flaky yang sudah dikenal dan tidak terkait perubahan sub-project ini (mis. tabrakan unique-constraint factory acak), jalankan ulang test itu sendirian untuk konfirmasi, catat sebagai flaky bukan regresi — JANGAN diam-diam diabaikan tanpa dicatat.

- [ ] **Step 6: Tulis handoff log**

Buat `.agents/logs/2026-08-24-refactor-02-keuangan-sp5-kategori-keringanan.md` (Bahasa Indonesia): ringkasan Task 1-3 dengan commit hash, hasil grep Step 1 (kosong), hasil test Step 3 dan Step 5 (angka pasti, jangan dicampur).

- [ ] **Step 7: Update `.agents/plans/2026-08-20-1800-master-refactor-domain-pattern.md` §6**

Tambahkan baris baru "Migrasi Domain Keuangan Sub-project 5 (Mini — Kategori & Siswa Keringanan, penutup celah audit SP4)" dengan link ke spec/plan/log, status 🟢 SELESAI.

- [ ] **Step 8: Commit**

```bash
git add .agents/logs/2026-08-24-refactor-02-keuangan-sp5-kategori-keringanan.md .agents/plans/2026-08-20-1800-master-refactor-domain-pattern.md
git commit -m "docs(refactor): handoff log migrasi domain Keuangan Sub-project 5 (mini, penutup celah kategori/siswa keringanan)"
```
