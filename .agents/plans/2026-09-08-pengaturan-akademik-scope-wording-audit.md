# Kejujuran Wording & UX Akses Tanpa Lembaga Aktif — Menu Pengaturan Akademik Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ganti redirect paksa ke `/dashboard` (saat lembaga aktif belum dipilih) jadi empty-state in-page, dan tampilkan nama lembaga yang sedang dikonfigurasi di header — menu "Pengaturan Akademik" TIDAK butuh perubahan backend logic scope sama sekali (sudah solid).

**Architecture:** 1 perubahan controller (`index()` berhenti redirect, selalu return `View`) + 1 perubahan view (badge + bungkus konten tab dengan `@if ($lembagaBelumDipilih)`). Pola `$lembagaBelumDipilih` mereplikasi preseden yang SUDAH ADA di `pembayaran/index.blade.php`/`tagihan/index.blade.php`, cuma dengan kartu empty-state versi design system modern.

**Tech Stack:** Laravel 12, Blade, Pest (function-style test).

## Global Constraints

- `updateHariAktif()`/`updateBatasEditAbsen()` (endpoint AJAX) TIDAK BOLEH diubah — sudah benar (422 JSON, bukan redirect halaman).
- `index()` SETELAH fix TIDAK PERNAH redirect — return type murni `View`, bukan `View|RedirectResponse`. Import `Illuminate\Http\RedirectResponse` WAJIB dihapus dari file (satu-satunya pemakaian ada di signature `index()` yang diubah).
- Badge nama lembaga (Item 1) HANYA muncul saat `! ($lembagaBelumDipilih ?? false)` — SATU warna (brand), TIDAK PERNAH varian "Semua Lembaga" ungu (halaman ini tidak punya konsep agregat).
- Skenario "tidak ada lembaga aktif sama sekali" DAN "session `active_lembaga_id` stale/lintas-yayasan" mendapat perlakuan IDENTIK (`resolveActiveLembagaId()` mengembalikan `null` untuk keduanya) — TIDAK PERLU dan TIDAK BOLEH dibedakan pesannya.
- Struktur closing `</div>` blok tab yang sudah ada (baris ±17-288 di file asli) TIDAK BOLEH diubah isinya — hanya dibungkus `@if`/`@else`/`@endif` di sekelilingnya, JANGAN menambah/mengurangi tag apa pun di dalamnya.

---

## Konteks File yang Sudah Ada (baca sebelum mulai)

- `app/Http/Controllers/Admin/PengaturanAkademikController.php` — `index()` saat ini `View|RedirectResponse`, redirect ke `route('dashboard')` kalau `resolveActiveLembagaId()` null. `updateHariAktif()`/`updateBatasEditAbsen()` TIDAK disentuh plan ini.
- `resources/views/portals/lembaga/akademik/pengaturan/akademik.blade.php` — 291 baris. Header di baris 10-15. Blok tab (`x-data="{ tab: 'hari-aktif' }"`) dari baris 17 sampai `</div>` penutupnya di baris 288 — TIDAK disentuh isinya, cuma dibungkus.
- `tests/Feature/Admin/PengaturanAkademikControllerTest.php` — 2 test SUDAH ADA yang WAJIB DIUBAH (bukan ditambah baru): baris 194 ("redirects a yayasan-scoped user without an active lembaga away from the pengaturan akademik page") dan baris 208 ("menolak actor yayasan dengan active_lembaga_id stale mengakses Pengaturan Akademik") — keduanya saat ini `assertRedirect(...)`, harus diubah jadi `assertOk()`.
- Preseden pola: `resources/views/portals/lembaga/keuangan/pembayaran/index.blade.php` baris 9-12 dan `tagihan/index.blade.php` baris 9-12 — SUDAH pakai `@if ($lembagaBelumDipilih ?? false)` tanpa pernah redirect, TAPI pakai design system LAMA (`x-panel`/`bg-signal-amber`) — JANGAN dicontoh stylingnya, cuma pola flag/struktur-nya.

---

### Task 1: Controller — `index()` Berhenti Redirect, Selalu Render View

**Files:**
- Modify: `app/Http/Controllers/Admin/PengaturanAkademikController.php`
- Modify: `tests/Feature/Admin/PengaturanAkademikControllerTest.php`

**Interfaces:**
- Produces: view `portals.lembaga.akademik.pengaturan.akademik` SELALU menerima variabel `lembagaBelumDipilih` (bool). Saat `true`: `lembaga` adalah `null`, `entriList` adalah `collect()` kosong, `bolehNasional`/`bolehKelolaHariAktif` adalah `false`. Saat `false`: perilaku identik dengan sebelumnya.

- [x] **Step 1: Ubah 2 test existing supaya gagal terhadap kode lama**

Di `tests/Feature/Admin/PengaturanAkademikControllerTest.php`, cari test (baris ±194):

```php
it('redirects a yayasan-scoped user without an active lembaga away from the pengaturan akademik page', function () {
    Permission::firstOrCreate(['name' => 'kalender-akademik.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->syncPermissions(['kalender-akademik.view']);

    $manager = User::factory()->create(['lembaga_id' => null]);
    $manager->assignRole($role);

    $this->actingAs($manager)
        ->get(route('admin.pengaturan.akademik.index'))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasErrors('lembaga_id');
});
```

Ganti SELURUHNYA jadi:

```php
it('shows an in-page prompt instead of redirecting when a yayasan-scoped user has no active lembaga', function () {
    Permission::firstOrCreate(['name' => 'kalender-akademik.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->syncPermissions(['kalender-akademik.view']);

    $manager = User::factory()->create(['lembaga_id' => null]);
    $manager->assignRole($role);

    $this->actingAs($manager)
        ->get(route('admin.pengaturan.akademik.index'))
        ->assertOk()
        ->assertViewIs('portals.lembaga.akademik.pengaturan.akademik')
        ->assertViewHas('lembagaBelumDipilih', true)
        ->assertSee('Pilih Lembaga Aktif Dulu');
});
```

Lalu cari test (baris ±208):

```php
it('menolak actor yayasan dengan active_lembaga_id stale mengakses Pengaturan Akademik', function () {
    $yayasanSaya = Yayasan::factory()->create();
    $yayasanLain = Yayasan::factory()->create();
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasanLain->id]);
    Permission::firstOrCreate(['name' => 'kalender-akademik.view', 'guard_name' => 'web']);
    $manager = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasanSaya->id]);
    $manager->givePermissionTo('kalender-akademik.view');
    $role = Role::firstOrCreate(['name' => 'yayasan_uji_pengaturan', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $manager->assignRole($role);
    session(['active_lembaga_id' => $lembagaLain->id]);

    $response = $this->actingAs($manager)->get(route('admin.pengaturan.akademik.index'));

    $response->assertRedirect();
    $response->assertSessionHasErrors('lembaga_id');
});
```

Ganti SELURUHNYA jadi:

```php
it('shows the same in-page prompt when active_lembaga_id session is stale (belongs to a different yayasan)', function () {
    $yayasanSaya = Yayasan::factory()->create();
    $yayasanLain = Yayasan::factory()->create();
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasanLain->id]);
    Permission::firstOrCreate(['name' => 'kalender-akademik.view', 'guard_name' => 'web']);
    $manager = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasanSaya->id]);
    $manager->givePermissionTo('kalender-akademik.view');
    $role = Role::firstOrCreate(['name' => 'yayasan_uji_pengaturan', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $manager->assignRole($role);
    session(['active_lembaga_id' => $lembagaLain->id]);

    $response = $this->actingAs($manager)->get(route('admin.pengaturan.akademik.index'));

    $response->assertOk();
    $response->assertViewHas('lembagaBelumDipilih', true);
    $response->assertSee('Pilih Lembaga Aktif Dulu');
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="in-page prompt" --compact`
Expected: FAIL — kode `index()` saat ini masih redirect ke `route('dashboard')`, `assertOk()` gagal (response 302).

- [x] **Step 3: Implementasi minimal**

Di `app/Http/Controllers/Admin/PengaturanAkademikController.php`, hapus import (baris 14):

```php
use Illuminate\Http\RedirectResponse;
```

(SATU-SATUNYA pemakaian import ini di file — `updateHariAktif()`/`updateBatasEditAbsen()` return `JsonResponse`, bukan `RedirectResponse` — dikonfirmasi lewat grep sebelum spec ditulis.)

Ganti method `index()`:

```php
public function index(Request $request): View|RedirectResponse
{
    $this->authorize('kalender-akademik.view');

    $lembagaId = $this->resolveActiveLembagaId($request->user());
    if ($lembagaId === null) {
        return redirect()->route('dashboard')
            ->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga untuk mengakses Pengaturan Akademik.']);
    }

    $lembaga = Lembaga::findOrFail($lembagaId);

    return view('portals.lembaga.akademik.pengaturan.akademik', [
        'lembaga' => $lembaga,
        'entriList' => KalenderAkademik::where(fn ($q) => $q->whereNull('lembaga_id')->orWhere('lembaga_id', $lembagaId))
            ->orderBy('tanggal')
            ->get(),
        'bolehNasional' => $request->user()->can('kalender-akademik.kelola-nasional'),
        'bolehKelolaHariAktif' => $request->user()->can('pengaturan-akademik.kelola'),
    ]);
}
```

menjadi:

```php
public function index(Request $request): View
{
    $this->authorize('kalender-akademik.view');

    $lembagaId = $this->resolveActiveLembagaId($request->user());

    if ($lembagaId === null) {
        return view('portals.lembaga.akademik.pengaturan.akademik', [
            'lembagaBelumDipilih' => true,
            'lembaga' => null,
            'entriList' => collect(),
            'bolehNasional' => false,
            'bolehKelolaHariAktif' => false,
        ]);
    }

    $lembaga = Lembaga::findOrFail($lembagaId);

    return view('portals.lembaga.akademik.pengaturan.akademik', [
        'lembagaBelumDipilih' => false,
        'lembaga' => $lembaga,
        'entriList' => KalenderAkademik::where(fn ($q) => $q->whereNull('lembaga_id')->orWhere('lembaga_id', $lembagaId))
            ->orderBy('tanggal')
            ->get(),
        'bolehNasional' => $request->user()->can('kalender-akademik.kelola-nasional'),
        'bolehKelolaHariAktif' => $request->user()->can('pengaturan-akademik.kelola'),
    ]);
}
```

- [x] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="in-page prompt" --compact`
Expected: FAIL LAGI di titik ini — TAPI dengan error BEDA (bukan lagi soal redirect, melainkan `assertSee('Pilih Lembaga Aktif Dulu')` gagal karena teks itu belum ada di Blade). Ini NORMAL dan DIHARAPKAN — Task 1 baru menyelesaikan sisi controller, teks empty-state-nya baru ditambahkan di Task 2. JANGAN anggap ini kegagalan Task 1; lanjut ke Task 2 sebelum menjalankan ulang test ini.

- [x] **Step 5: Jalankan regresi test file ini secara penuh (abaikan 2 test yang masih gagal di titik ini)**

Run: `php artisan test tests/Feature/Admin/PengaturanAkademikControllerTest.php --compact`
Expected: SEMUA test LAIN (di luar 2 test "in-page prompt" yang baru diubah) tetap PASS — termasuk "shows the acting lembaga-scoped user's own hari_libur_mingguan..." dan "renders the pengaturan akademik page for an authorized user" (kasus normal, lembaga SUDAH aktif, TIDAK terpengaruh perubahan ini). 2 test "in-page prompt" boleh MASIH gagal di titik ini (lihat Step 4) — akan hijau setelah Task 2.

- [x] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/PengaturanAkademikController.php tests/Feature/Admin/PengaturanAkademikControllerTest.php
git commit -m "fix(pengaturan-akademik): index() tidak lagi redirect ke dashboard saat lembaga belum dipilih"
```

---

### Task 2: View — Badge Nama Lembaga + Kartu Empty-State

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/pengaturan/akademik.blade.php`

**Interfaces:**
- Consumes: `$lembagaBelumDipilih` (bool), `$lembaga` (`?Lembaga`) dari Task 1.

- [x] **Step 1: Jalankan test dari Task 1, konfirmasi masih gagal (baseline)**

Run: `php artisan test --filter="in-page prompt" --compact`
Expected: FAIL — `assertSee('Pilih Lembaga Aktif Dulu')` belum ketemu (teks belum ada di Blade). Ini konfirmasi baseline sebelum Step 2 di bawah, BUKAN test baru.

- [x] **Step 2: Implementasi — badge header**

Di `resources/views/portals/lembaga/akademik/pengaturan/akademik.blade.php`, ganti (baris 10-15):

```blade
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Pengaturan Akademik</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Pengaturan Akademik</b>
            </p>
        </div>
```

menjadi:

```blade
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="font-display text-lg font-bold text-gray-900">Pengaturan Akademik</h1>
                @if (! ($lembagaBelumDipilih ?? false))
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-brand-200 bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-700">
                        <x-icon name="apartment" class="h-3.5 w-3.5" />
                        {{ $lembaga->nama }}
                    </span>
                @endif
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Pengaturan Akademik</b>
            </p>
        </div>
```

- [x] **Step 3: Implementasi — bungkus blok tab dengan empty-state**

Di file yang sama, cari baris pembuka blok tab:

```blade
        <div x-data="{ tab: 'hari-aktif' }">
```

Ganti jadi:

```blade
        @if ($lembagaBelumDipilih ?? false)
            <div class="rounded-2xl border-2 border-dashed border-gray-200 p-12 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                    <x-icon name="apartment" class="h-6 w-6" />
                </div>
                <h3 class="mt-4 font-display text-sm font-semibold text-gray-900">Pilih Lembaga Aktif Dulu</h3>
                <p class="mx-auto mt-1 max-w-md text-sm text-gray-500">Hari Aktif Sekolah, Batas Waktu Edit Presensi, dan Kalender Akademik diatur per lembaga. Pilih 1 lembaga lewat pengalih lembaga di pojok kanan atas untuk mulai mengatur.</p>
            </div>
        @else
        <div x-data="{ tab: 'hari-aktif' }">
```

**JANGAN mengubah apa pun di dalam blok tab** (isi tetap persis sama dari baris ini sampai penutupnya). Cari baris penutup blok tab di akhir file:

```blade
        </div>
        </div>
    </div>
</x-app-layout>
```

Ganti jadi (tambahkan `@endif` SETELAH penutup `x-data="{ tab: ... }"` yang kedua dari bawah, SEBELUM penutup `</div>` milik wrapper terluar `mx-auto max-w-6xl`):

```blade
        </div>
        </div>
        @endif
    </div>
</x-app-layout>
```

**Verifikasi struktur WAJIB sebelum commit**: hitung ulang jumlah `<div>` pembuka vs `</div>` penutup di seluruh file — HARUS SAMA seperti sebelum edit (2 `</div>` yang sudah ada di baris 287-288 TETAP ada apa adanya, cuma ditambah 1 baris `@endif` baru setelahnya). Kalau editor/IDE punya fitur "match bracket"/"fold", pakai itu untuk memastikan tidak ada tag yang tertinggal terbuka.

- [x] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="in-page prompt" --compact`
Expected: PASS kedua test.

- [x] **Step 5: Tulis test yang gagal — badge Item 1**

Tambahkan ke `tests/Feature/Admin/PengaturanAkademikControllerTest.php` (di akhir file):

```php
it('shows the lembaga name badge in the header when an active lembaga is set', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SMK Cakra Buana']);
    $manager = actingAsPengaturanAkademikManager($lembaga, ['kalender-akademik.view']);

    $this->actingAs($manager)
        ->get(route('admin.pengaturan.akademik.index'))
        ->assertOk()
        ->assertSee('SMK Cakra Buana');
});
```

- [x] **Step 6: Jalankan test, pastikan gagal lalu lulus**

Run: `php artisan test --filter="lembaga name badge in the header" --compact`
Expected: test ini kemungkinan SUDAH PASS begitu Step 2 selesai (badge sudah diimplementasikan lebih dulu di step itu). Kalau sudah PASS, itu NORMAL — Step 5 di sini murni menambahkan cakupan test permanen untuk regresi ke depan, bukan TDD murni step-demi-step untuk fitur ini (fiturnya sudah selesai di Step 2-3, test ini menyusul sebagai dokumentasi/regresi).

- [x] **Step 7: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/PengaturanAkademikControllerTest.php --compact`
Expected: PASS SEMUA test di file ini.

- [x] **Step 8: Commit**

```bash
git add resources/views/portals/lembaga/akademik/pengaturan/akademik.blade.php tests/Feature/Admin/PengaturanAkademikControllerTest.php
git commit -m "feat(pengaturan-akademik): badge nama lembaga + kartu empty-state pilih lembaga"
```

---

### Task 3: Penutup — Regresi Penuh & Pint

**Files:**
- Tidak ada file baru — task verifikasi murni.

- [x] **Step 1: Jalankan seluruh test yang menyentuh Pengaturan Akademik & Kalender Akademik**

Run: `php artisan test --compact --filter="PengaturanAkademikControllerTest|KalenderAkademikCrudTest|CreateKalenderAkademikActionTest|KalenderAkademikTest|KalenderAkademikResolverTest"`
Expected: PASS semua, 0 gagal.

- [x] **Step 2: Jalankan Pint pada file yang diubah**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `{"tool":"pint","result":"passed"}`.

- [x] **Step 3: Verifikasi manual cepat via browser (opsional tapi disarankan)**

Login sebagai yayasan-scope TANPA switch lembaga: buka `/admin/pengaturan/akademik` — HARUS tetap di halaman ini (bukan ter-lempar ke dashboard), lihat kartu "Pilih Lembaga Aktif Dulu". Switch ke 1 lembaga lewat topbar: halaman yang sama sekarang menampilkan badge nama lembaga di header dan konten tab Hari Aktif/Kalender normal seperti sebelumnya.

- [x] **Step 4: Laporkan hasil**

TIDAK perlu menulis file handoff log baru di task ini — sama seperti plan-plan sebelumnya di rangkaian audit ini, kalau user menghendaki log terpisah, itu permintaan tambahan setelah plan ini selesai.

---

## Self-Review

**1. Spec coverage** — kedua item spec `.agents/specs/2026-09-08-pengaturan-akademik-scope-wording-audit.md` tercakup: Item 2 (backend `index()` + 2 test existing diubah) di Task 1, Item 1 (badge) + Item 2 (view empty-state) di Task 2. "Di Luar Scope" spec (`updateHariAktif()`/`updateBatasEditAbsen()`, rombak visual identik pembayaran/tagihan, ekstrak helper bersama) sengaja tidak ada task-nya.

**2. Placeholder scan** — tidak ada "TBD"/dst. Semua step berisi kode lengkap siap tempel.

**3. Type consistency** — `index(): View` (bukan lagi `View|RedirectResponse`) konsisten dipakai di Task 1 Step 3; variabel `$lembagaBelumDipilih`/`$lembaga` dipakai konsisten namanya antara controller (Task 1) dan view (Task 2).

**Catatan tambahan hasil self-review**:
- Task 1 Step 4 SENGAJA mendokumentasikan bahwa test masih gagal setelah Task 1 selesai (dengan alasan berbeda dari sebelumnya) — ini BUKAN kesalahan penulisan plan, melainkan konsekuensi alami dari memecah 1 perubahan (controller + view) jadi 2 task terpisah untuk kejelasan review. Pelaksana WAJIB membaca catatan di Step 4 itu supaya tidak salah mengira Task 1 gagal.
- Task 2 Step 6 mencatat kemungkinan test "PASS lebih awal" (begitu Step 2 selesai, sebelum Step 5 secara eksplisit menulisnya) — pola yang sama seperti ditemukan di plan-plan audit sebelumnya (Kelas, Mata Pelajaran), bukan indikasi kesalahan.
- Task 2 Step 3 memberi instruksi EKSPLISIT untuk memverifikasi keseimbangan tag `<div>` sebelum commit — ini PENTING karena edit ini menyisipkan `@if`/`@else`/`@endif` di SEKELILING blok besar (272 baris) tanpa menyentuh isinya, risiko human/agent error tertinggi di seluruh plan ini ada di titik ini (salah taruh 1 tag pembungkus bisa merusak seluruh halaman tanpa error PHP yang jelas, cuma tampilan Blade yang berantakan).
