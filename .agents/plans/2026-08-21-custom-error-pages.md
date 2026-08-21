# Halaman Error Custom Bergaya Pintera Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ganti halaman error default Laravel (403/404/419/422/429/500/503) dengan 7 halaman custom bergaya Pintera — ikon outline lembut, brand identity dari `guest.blade.php`, copy Bahasa Indonesia yang hangat, tombol aksi auth-aware.

**Architecture:** 1 Blade component reusable (`resources/views/components/error-page.blade.php`, dipakai via `<x-error-page ... />`) yang merender full-page HTML, dipanggil dari 7 view tipis di `resources/views/errors/{403,404,419,422,429,500,503}.blade.php`. Laravel secara otomatis merender `resources/views/errors/{code}.blade.php` untuk HTTP exception apapun yang statusnya cocok — tidak ada registrasi manual di `bootstrap/app.php`/`Handler`.

**Tech Stack:** Laravel 11 Blade, Tailwind CSS (token `ink`/`paper`/`brass`/`brand` sudah ada di `tailwind.config.js`), Pest (`php artisan test`).

## Global Constraints

- Spec sumber: `.agents/specs/2026-08-21-custom-error-pages.md` — baca file ini kalau ada keraguan.
- **TIDAK ADA perubahan logic/controller/middleware/route** — murni Blade view + 1 component baru + 3 `@case` baru di `icon.blade.php`.
- **JANGAN sentuh** `resources/views/layouts/guest.blade.php` (cuma dicontoh gayanya), Material Symbols font loading, atau 3 file test independen (`GuruBkFieldsTest`, `GuruCrudTest`, `KaryawanCrudTest`).
- **Halaman 401 TIDAK dibuat** — di luar cakupan (keputusan eksplisit spec §8).
- Scoped test per task. **Full suite HANYA di Task 4 (task terakhir), HARUS minta izin user dulu sebelum dijalankan.**
- **Cara verifikasi render halaman error yang WAJIB dipakai di semua task testing:** daftarkan route sementara di dalam test itu sendiri via `Route::get('/uji-error/{code}', fn () => abort({code}));`, lalu `$this->get(...)`. Laravel me-resolve view error MURNI berdasarkan kode status HTTP (`View::exists("errors::{code}")`), bukan berdasarkan jenis exception spesifik — jadi `abort({code})` di route sementara SAH dan RELIABLE untuk membuktikan view custom ter-render, tanpa perlu mensimulasikan CSRF asli/rate-limit asli/maintenance-mode asli yang lebih rapuh dan bergantung state lain. (Dikonfirmasi: `bootstrap/app.php` proyek ini punya `$middleware->validateCsrfTokens(...)` aktif, tapi CSRF middleware Laravel otomatis di-skip saat `app()->runningUnitTests()` bernilai true — jadi mensimulasikan 419 lewat POST tanpa token TIDAK AKAN bekerja di test suite ini; `abort(419)` di route sementara adalah cara yang benar.)
- **Catatan penting soal 422** (WAJIB masuk ke laporan task, bukan diam-diam diabaikan): pada request HTML biasa (bukan JSON), `ValidationException` bawaan Laravel TIDAK merender halaman 422 — dia melakukan REDIRECT 302 kembali ke halaman sebelumnya dengan error di-flash ke session (perilaku standar `$request->validate()`). Artinya halaman `errors/422.blade.php` yang dibuat plan ini HAMPIR TIDAK PERNAH terlihat user lewat form submit biasa — dia cuma akan ter-render kalau ada kode yang eksplisit `abort(422)` atau kasus non-standar lain. Ini BUKAN bug plan ini, ini karakteristik bawaan Laravel — halaman tetap dibuat sesuai permintaan spec (untuk kelengkapan & kalau suatu saat dibutuhkan), tapi jangan kaget kalau tidak pernah ketemu 422 page ini secara alami saat browsing.
- **Executor plan ini KEMUNGKINAN adalah sesi/agent lain** tanpa akses ke percakapan yang menulis spec/plan — setiap task berisi kode Blade lengkap dan command verifikasi nyata.

---

### Task 1: Tambah 3 Ikon Baru ke `icon.blade.php`

**Files:**
- Modify: `resources/views/components/icon.blade.php`

**Interfaces:**
- Produces: `<x-icon name="book_search" />`, `<x-icon name="server" />`, `<x-icon name="build" />` — dipakai Task 3.

- [ ] **Step 1: Sisipkan 3 `@case` baru**

Cari baris berikut di `resources/views/components/icon.blade.php` (case terakhir sebelum `@default`):

```blade
    @case('receipt')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/><line x1="8" y1="8" x2="16" y2="8"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="8" y1="16" x2="12" y2="16"/></svg>
        @break

    @default
```

Ganti jadi (sisipkan 3 `@case` baru DI ANTARA `@break` milik `receipt` dan `@default`):

```blade
    @case('receipt')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/><line x1="8" y1="8" x2="16" y2="8"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="8" y1="16" x2="12" y2="16"/></svg>
        @break

    @case('book_search')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><path d="M2 5.5C2 4 3.5 3 5.5 3H11v14H5.5C3.5 17 2 18 2 19.5V5.5Z"/><path d="M22 5.5C22 4 20.5 3 18.5 3H13v14h5.5c2 0 3.5 1 3.5 2.5V5.5Z"/><circle cx="17.3" cy="14.3" r="2.2"/><path d="M19 15.9 20.6 17.5"/></svg>
        @break

    @case('server')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><rect x="3" y="4" width="18" height="6" rx="1.5"/><rect x="3" y="14" width="18" height="6" rx="1.5"/><circle cx="7" cy="7" r="0.8" fill="currentColor" stroke="none"/><circle cx="7" cy="17" r="0.8" fill="currentColor" stroke="none"/><path d="M12 10.5v3"/></svg>
        @break

    @case('build')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><path d="M14.7 6.3a4 4 0 0 1-5.4 5.4L4 17l3 3 5.3-5.3a4 4 0 0 1 5.4-5.4l-2.6 2.6-2-2 2.6-2.6Z"/></svg>
        @break

    @default
```

- [ ] **Step 2: Verifikasi manual — ikon baru bisa dipanggil tanpa error**

```bash
php artisan tinker --execute="echo view('components.icon', ['name' => 'book_search'])->render();"
php artisan tinker --execute="echo view('components.icon', ['name' => 'server'])->render();"
php artisan tinker --execute="echo view('components.icon', ['name' => 'build'])->render();"
```

Expected: masing-masing mencetak markup `<svg ...>...</svg>` tanpa exception. Kalau ada `ParseError`/`Undefined variable`, cek ulang penempatan `@case`/`@break` (pastikan berada DI DALAM blok `@switch($name)` yang sudah ada, bukan di luar).

- [ ] **Step 3: Jalankan scoped test (kalau ada test existing untuk komponen ikon)**

```bash
php artisan test --filter=Icon
```

Expected: PASS (atau "No tests found" kalau memang belum ada test untuk komponen ini — itu OK, tidak wajib dibuatkan test baru untuk komponen ikon murni, cukup verifikasi manual Step 2).

- [ ] **Step 4: Commit**

```bash
git add resources/views/components/icon.blade.php
git commit -m "feat(ui): tambah ikon book_search, server, build untuk halaman error custom"
```

---

### Task 2: Buat Component `error-page.blade.php`

**Files:**
- Create: `resources/views/components/error-page.blade.php`

**Interfaces:**
- Consumes: `<x-icon :name="$icon" />` dari Task 1.
- Produces: `<x-error-page code="..." icon="..." title="..." message="..." />` — dipakai Task 3 di 7 file `resources/views/errors/*.blade.php`.

- [ ] **Step 1: Buat file component**

Buat `resources/views/components/error-page.blade.php` dengan isi lengkap berikut (reuse identitas visual `resources/views/layouts/guest.blade.php` — gradient, brand mark, kartu putih; tombol auth-aware pakai pola kelas Tailwind yang sama dengan `resources/views/components/link-button.blade.php` variant `primary`, yaitu `bg-brand-500` / `hover:bg-brand-600`):

```blade
@props(['code', 'icon', 'title', 'message'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $code }} — {{ $title }} | {{ config('app.name', 'Pintera') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:600,700,800|inter:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-ink antialiased">
        <div class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden bg-gradient-to-br from-ink via-[#123363] to-ink px-4 py-10">
            <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-white/10 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-16 -left-16 h-72 w-72 rounded-full bg-brass/20 blur-3xl"></div>

            <a href="/" class="relative z-10 flex items-center gap-3">
                <span class="flex h-14 w-14 items-center justify-center rounded-xl border border-brass/50 bg-white/5 font-display text-2xl font-bold text-brass">
                    {{ Str::of(config('app.name', 'Y'))->substr(0, 1) }}
                </span>
                <span class="leading-tight text-paper">
                    <span class="block font-display text-lg font-bold">{{ config('app.name', 'Yayasan') }}</span>
                    <span class="block text-[11px] uppercase tracking-[0.14em] text-paper/40">Sistem Administrasi</span>
                </span>
            </a>

            <div class="relative z-10 mt-8 w-full overflow-hidden rounded-2xl border border-white/10 bg-white shadow-elevated sm:max-w-md">
                <div class="flex flex-col items-center gap-4 px-8 py-10 text-center">
                    <span class="flex h-16 w-16 items-center justify-center rounded-full bg-brass/10 text-brass">
                        <x-icon :name="$icon" class="h-8 w-8" />
                    </span>

                    <p class="font-display text-5xl font-bold text-ink">{{ $code }}</p>

                    <h1 class="font-display text-xl font-bold text-ink">{{ $title }}</h1>

                    <p class="max-w-xs text-sm text-gray-500">{{ $message }}</p>

                    @auth
                        <a href="{{ route('dashboard') }}" class="mt-2 inline-flex items-center gap-2 rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-600 active:scale-[0.98]">
                            Kembali ke Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="mt-2 inline-flex items-center gap-2 rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-600 active:scale-[0.98]">
                            Ke Halaman Login
                        </a>
                    @endauth
                </div>
            </div>
        </div>
    </body>
</html>
```

- [ ] **Step 2: Verifikasi manual — component bisa dirender berdiri sendiri**

```bash
php artisan tinker --execute="echo str_contains(view('components.error-page', ['code' => 403, 'icon' => 'lock', 'title' => 'Akses Dibatasi', 'message' => 'Contoh pesan.'])->render(), 'Akses Dibatasi') ? 'OK' : 'GAGAL';"
```

Expected: `OK`.

- [ ] **Step 3: Commit**

```bash
git add resources/views/components/error-page.blade.php
git commit -m "feat(ui): buat component error-page reusable untuk halaman error custom"
```

---

### Task 3: Buat 7 View Error + Test Render per Kode

**Files:**
- Create: `resources/views/errors/403.blade.php`
- Create: `resources/views/errors/404.blade.php`
- Create: `resources/views/errors/419.blade.php`
- Create: `resources/views/errors/422.blade.php`
- Create: `resources/views/errors/429.blade.php`
- Create: `resources/views/errors/500.blade.php`
- Create: `resources/views/errors/503.blade.php`
- Create: `tests/Feature/ErrorPagesTest.php`

**Interfaces:**
- Consumes: `<x-error-page ... />` dari Task 2.
- Produces: ketujuh view ini dipakai OTOMATIS oleh Laravel (bukan dipanggil manual dari kode manapun) — Task 4 mengonsumsi pola test yang sama untuk skenario auth-aware.

- [ ] **Step 1: Buat `resources/views/errors/403.blade.php`**

```blade
<x-error-page
    code="403"
    icon="lock"
    title="Akses Dibatasi"
    message="Halaman ini khusus untuk peran tertentu. Kalau menurut Anda ini keliru, hubungi admin sekolah Anda."
/>
```

- [ ] **Step 2: Buat `resources/views/errors/404.blade.php`**

```blade
<x-error-page
    code="404"
    icon="book_search"
    title="Halaman Tidak Ditemukan"
    message="Halaman yang Anda cari mungkin sudah dipindahkan atau tidak tersedia lagi."
/>
```

- [ ] **Step 3: Buat `resources/views/errors/419.blade.php`**

```blade
<x-error-page
    code="419"
    icon="schedule"
    title="Sesi Anda Berakhir"
    message="Demi keamanan, sesi otomatis berakhir setelah tidak aktif. Silakan masuk kembali untuk melanjutkan."
/>
```

- [ ] **Step 4: Buat `resources/views/errors/422.blade.php`**

```blade
<x-error-page
    code="422"
    icon="checklist"
    title="Periksa Kembali Data Anda"
    message="Beberapa data yang dikirim belum sesuai. Silakan periksa kembali formulirnya."
/>
```

- [ ] **Step 5: Buat `resources/views/errors/429.blade.php`**

```blade
<x-error-page
    code="429"
    icon="hourglass_top"
    title="Terlalu Banyak Permintaan"
    message="Sistem sedang menerima banyak aktivitas dari perangkat Anda. Mohon tunggu sebentar lalu coba lagi."
/>
```

- [ ] **Step 6: Buat `resources/views/errors/500.blade.php`**

```blade
<x-error-page
    code="500"
    icon="server"
    title="Ada Gangguan di Sistem"
    message="Tim kami sedang menangani masalah ini. Silakan coba lagi dalam beberapa saat."
/>
```

- [ ] **Step 7: Buat `resources/views/errors/503.blade.php`**

```blade
<x-error-page
    code="503"
    icon="build"
    title="Sedang Dalam Perawatan"
    message="Kami sedang melakukan pemeliharaan untuk pengalaman yang lebih baik. Silakan kembali sebentar lagi."
/>
```

- [ ] **Step 8: Grep dulu — pastikan tidak ada test lain yang akan bentrok**

```bash
grep -rn "assertSee.*Forbidden\|assertSee.*Page Not Found\|assertSee.*Page Expired\|assertSee.*Too Many Requests\|assertSee.*Server Error\|assertSee.*Service Unavailable" tests/
```

Expected: TIDAK ADA output. Kalau ADA, baca test itu dulu — kemungkinan besar test itu meng-assert markup default Laravel yang sekarang akan diganti; sesuaikan assertion-nya ke teks baru dari tabel §4 spec SEBELUM lanjut ke step berikutnya.

- [ ] **Step 9: Buat test render untuk ketujuh kode**

Buat `tests/Feature/ErrorPagesTest.php`:

```php
<?php

use Illuminate\Support\Facades\Route;

it('renders the custom 403 page with its icon-badge layout and copy', function () {
    Route::get('/uji-error/403', fn () => abort(403));

    $response = $this->get('/uji-error/403');

    $response->assertStatus(403);
    $response->assertSee('403');
    $response->assertSee('Akses Dibatasi');
    $response->assertSee('khusus untuk peran tertentu', false);
});

it('renders the custom 404 page for a route that genuinely does not exist', function () {
    $response = $this->get('/rute-tidak-pernah-ada-untuk-uji-404');

    $response->assertStatus(404);
    $response->assertSee('404');
    $response->assertSee('Halaman Tidak Ditemukan');
    $response->assertSee('sudah dipindahkan atau tidak tersedia', false);
});

it('renders the custom 419 page', function () {
    Route::get('/uji-error/419', fn () => abort(419));

    $response = $this->get('/uji-error/419');

    $response->assertStatus(419);
    $response->assertSee('419');
    $response->assertSee('Sesi Anda Berakhir');
    $response->assertSee('sesi otomatis berakhir', false);
});

it('renders the custom 422 page', function () {
    Route::get('/uji-error/422', fn () => abort(422));

    $response = $this->get('/uji-error/422');

    $response->assertStatus(422);
    $response->assertSee('422');
    $response->assertSee('Periksa Kembali Data Anda');
    $response->assertSee('data yang dikirim belum sesuai', false);
});

it('renders the custom 429 page', function () {
    Route::get('/uji-error/429', fn () => abort(429));

    $response = $this->get('/uji-error/429');

    $response->assertStatus(429);
    $response->assertSee('429');
    $response->assertSee('Terlalu Banyak Permintaan');
    $response->assertSee('banyak aktivitas dari perangkat Anda', false);
});

it('renders the custom 500 page', function () {
    Route::get('/uji-error/500', fn () => abort(500));

    $response = $this->get('/uji-error/500');

    $response->assertStatus(500);
    $response->assertSee('500');
    $response->assertSee('Ada Gangguan di Sistem');
    $response->assertSee('sedang menangani masalah ini', false);
});

it('renders the custom 503 page', function () {
    Route::get('/uji-error/503', fn () => abort(503));

    $response = $this->get('/uji-error/503');

    $response->assertStatus(503);
    $response->assertSee('503');
    $response->assertSee('Sedang Dalam Perawatan');
    $response->assertSee('melakukan pemeliharaan', false);
});
```

- [ ] **Step 10: Jalankan test**

```bash
php artisan test tests/Feature/ErrorPagesTest.php
```

Expected: 7 test PASS. Kalau ada yang FAIL dengan pesan terkait view tidak ditemukan (`View [errors::403] not found`), cek ulang nama file di `resources/views/errors/` — HARUS persis `403.blade.php` dst (bukan `403.blade.php.blade.php` atau salah folder).

- [ ] **Step 11: Commit**

```bash
git add resources/views/errors/ tests/Feature/ErrorPagesTest.php
git commit -m "feat(ui): buat 7 halaman error custom (403/404/419/422/429/500/503) + test render"
```

---

### Task 4: Test Tombol Auth-Aware + Verifikasi Akhir + Full Suite

**Files:**
- Modify: `tests/Feature/ErrorPagesTest.php`

**Interfaces:**
- Consumes: seluruh hasil Task 1-3.

- [ ] **Step 1: Tambah 2 test skenario auth-aware**

Tambahkan ke akhir `tests/Feature/ErrorPagesTest.php` (setelah test terakhir `it('renders the custom 503 page', ...)`):

```php
it('shows the "Kembali ke Dashboard" button for an authenticated user hitting an error page', function () {
    Route::get('/uji-error/403-auth', fn () => abort(403));

    $user = \App\Models\User::factory()->create();

    $response = $this->actingAs($user)->get('/uji-error/403-auth');

    $response->assertStatus(403);
    $response->assertSee('Kembali ke Dashboard');
    $response->assertDontSee('Ke Halaman Login');
});

it('shows the "Ke Halaman Login" button for a guest hitting an error page', function () {
    Route::get('/uji-error/403-guest', fn () => abort(403));

    $response = $this->get('/uji-error/403-guest');

    $response->assertStatus(403);
    $response->assertSee('Ke Halaman Login');
    $response->assertDontSee('Kembali ke Dashboard');
});
```

- [ ] **Step 2: Jalankan test**

```bash
php artisan test tests/Feature/ErrorPagesTest.php
```

Expected: 9 test PASS (7 dari Task 3 + 2 baru).

- [ ] **Step 3: Grep menyeluruh — pastikan tidak ada sisa referensi ke halaman error default**

```bash
grep -rln "errors::" resources/views/ 2>/dev/null
```

Expected: TIDAK ADA output di luar 7 file yang baru dibuat sendiri (kalau ada file lain yang secara eksplisit memanggil `view('errors::xxx')`, itu di luar cakupan plan ini — laporkan ke user, jangan diubah tanpa instruksi).

```bash
ls resources/views/errors/
```

Expected: persis 7 file: `403.blade.php`, `404.blade.php`, `419.blade.php`, `422.blade.php`, `429.blade.php`, `500.blade.php`, `503.blade.php`.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/ErrorPagesTest.php
git commit -m "test(ui): tambah test tombol auth-aware di halaman error custom"
```

- [ ] **Step 5: Minta izin user untuk full suite**

**JANGAN jalankan langsung.** Tanyakan ke user secara eksplisit: *"Task 1-4 selesai, semua scoped test PASS (9/9 di ErrorPagesTest). Boleh saya jalankan full test suite (`php artisan test`) sekarang sebagai verifikasi akhir?"* Tunggu jawaban user sebelum lanjut ke Step 6.

- [ ] **Step 6: Full suite (hanya setelah izin didapat)**

```bash
php artisan test
```

Expected: SEMUA test PASS, 0 failed, 0 error, JUMLAH TOTAL lebih besar dari baseline (1895 + 9 test baru = minimal 1904). Kalau ada FAIL di luar `ErrorPagesTest.php`, kemungkinan besar test lama yang meng-assert markup default Laravel yang terlewat di Task 3 Step 8 — perbaiki test tersebut (BUKAN kode aplikasi).

Catatan: kalau full suite menunjukkan satu-dua test FAIL yang TIDAK terkait sama sekali dengan halaman error (nama test tidak menyebut error/403/404/dst), kemungkinan itu flaky test pre-existing (pola ini sudah beberapa kali terjadi di branch `rbac-v2` — mis. `KomponenPenilaianCrudTest` collision `random_int`, `RaporPdfDataBuilderTest` nama siswa Faker) — jalankan ulang test itu SENDIRIAN dulu untuk konfirmasi sebelum menganggapnya masalah nyata dari plan ini.

- [ ] **Step 7: Tulis handoff log**

Buat `.agents/logs/2026-08-21-custom-error-pages.md` (format mengikuti handoff log Spec 1/Spec 2 sebelumnya di folder yang sama): ringkasan per task, commit hash masing-masing, hasil full suite dengan angka pasti (bukan perkiraan), dan catatan soal karakteristik 422 (§ Global Constraints) supaya tidak dianggap bug oleh reviewer berikutnya.

- [ ] **Step 8: Laporkan hasil ke user**

Setelah full suite PASS dan handoff log tertulis, laporkan ringkas ke user: jumlah task selesai, jumlah commit, hasil full suite (jumlah test/assertion sebelum vs sesudah).
