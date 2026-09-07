# Badge Scope & Kejujuran Wording — Menu Tahun Ajaran Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Buat menu "Tahun Ajaran" jujur dan konsisten soal scope yayasan/lembaga yang sedang aktif (badge header, label per kartu, wording dialog, konteks modal) mengikuti pola yang sudah ada di Siswa/Karyawan/Guru, hapus 1 halaman mati, dan perbaiki 1 ikon yang salah render — TANPA menyentuh satu baris pun logic backend (`TenantScope`, query `index()`, `TahunAjaran::activate()`), yang sudah terkonfirmasi benar.

**Architecture:** Semua perubahan di lapisan Controller (1 helper privat baru, murni pass-through data) dan Blade view (badge kondisional, label kondisional, wording string, penghapusan 1 route+method+file). Pola badge scope mereplikasi PERSIS implementasi yang sudah ada di `KaryawanController`/`GuruController` sesi ini — tidak ada desain baru.

**Tech Stack:** Laravel 12, Blade, Pest (function-style test, bukan class-based).

## Global Constraints

- Backend (`TenantScope`, query `TahunAjaranController`, `TahunAjaran::activate()`) TIDAK BOLEH diubah sama sekali — spec ini murni frontend/wording/dead-code.
- Badge scope (Item 1) HARUS pola identik Karyawan/Guru: badge nama lembaga aktif (warna brand: `border-brand-200 bg-brand-50 text-brand-700`) atau "Semua Lembaga" (warna ungu: `border-purple-200 bg-purple-50 text-purple-700`), HANYA untuk aktor `widestScopeLevel() === 'yayasan'`.
- Label lembaga per kartu (Item 2) HANYA tampil saat `$isYayasan` true DAN `$activeLembaga` null (mode "Semua Lembaga") — disembunyikan di semua kondisi lain (lembaga-scope, atau yayasan-scope yang sudah switch ke 1 lembaga).
- `scopeHeaderData()` WAJIB memakai `resolveActiveLembagaId()` dari `ResolveLembagaScopeTrait` (BUKAN `resolveLembagaId()` trait yang sama) — varian `resolveLembagaId()` melempar `abort()` saat yayasan-scope belum pilih lembaga aktif, tidak cocok untuk badge yang harus tetap tampil sebagai "Semua Lembaga" alih-alih error.
- Item 5 (hapus halaman mati): permission `tahun-ajaran.create` di seeder TETAP DIPERTAHANKAN (dipakai otorisasi `store()`/`update()`/`@can` di Blade) — HANYA route, method controller `create()`, dan file view yang dihapus.
- Item 6 (ikon `date_range` → `calendar_month`) digabung ke edit Item 4 dalam 1 langkah karena berada di elemen `<h3>` yang sama persis — TIDAK dipecah jadi 2 edit terpisah pada file yang sama.

---

## Konteks File yang Sudah Ada (baca sebelum mulai)

- `app/Http/Controllers/Admin/TahunAjaranController.php` — sudah `use ResolveLembagaScopeTrait;`, sudah `use AuthorizesRequests;`. BELUM `use App\Models\Lembaga;`.
- `app/Domains/Akademik/Support/ResolveLembagaScopeTrait.php` — punya `resolveActiveLembagaId(User $actor): ?int` (tidak pernah `abort()`, mengembalikan `null` kalau tidak ada lembaga aktif) dan `resolveLembagaId(User $actor, ?int $lembagaIdDiminta): ?int` (BEDA — melempar `abort(422)` untuk yayasan-scope tanpa lembaga aktif). Plan ini SELALU memakai `resolveActiveLembagaId()`.
- `resources/views/admin/tahun-ajaran/index.blade.php` — SPA modal-based (Alpine `x-data` di root), TIDAK server-side pagination. Modal `_modal-tahun-ajaran.blade.php` dan `_modal-semester.blade.php` di-`@include` di baris akhir file, otomatis mewarisi variabel dari parent view (tidak perlu passing manual).
- Test yang sudah ada dan relevan:
  - `tests/Feature/Admin/TahunAjaranSemesterPanelTest.php` — Pest function-style, sudah punya helper `actingAsTahunAjaranManager(Lembaga $lembaga): User` (lembaga-scope actor, permission `tahun-ajaran.view/create/activate`, `semester.create/activate`). Test-test baru di plan ini ditambahkan ke file ini.
  - `tests/Feature/TahunAjaranActivationTest.php` — test level MODEL (`$baru->activate()` langsung, bukan lewat HTTP) untuk `TahunAjaran::activate()`. TIDAK disentuh plan ini (backend tidak berubah).
  - `tests/Feature/Admin/TahunAjaranSemesterFeatureTest.php`, `tests/Unit/TahunAjaranSeederTest.php`, `tests/Feature/SemesterActivationTest.php`, `tests/Unit/SemesterSeederTest.php` — regresi wajib dijalankan di Task 5, tidak dimodifikasi.

---

### Task 1: Controller — `scopeHeaderData()` + Wiring `index()`

**Files:**
- Modify: `app/Http/Controllers/Admin/TahunAjaranController.php`
- Test: `tests/Feature/Admin/TahunAjaranSemesterPanelTest.php`

**Interfaces:**
- Produces: `TahunAjaranController::scopeHeaderData(Request $request): array` mengembalikan `['isYayasan' => bool, 'activeLembaga' => ?Lembaga]` — dipakai oleh view di Task 2 dan Task 3 lewat variabel `$isYayasan`/`$activeLembaga` di scope Blade.

- [ ] **Step 1: Tulis test yang gagal — controller pass scope data ke view**

Tambahkan ke `tests/Feature/Admin/TahunAjaranSemesterPanelTest.php` (di akhir file, sebelum baris terakhir):

```php
it('passes isYayasan=true and activeLembaga=null to the index view in "Semua Lembaga" mode', function () {
    $permissions = ['tahun-ajaran.view', 'tahun-ajaran.create', 'tahun-ajaran.activate', 'semester.create', 'semester.activate'];
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo($permissions);

    $yayasan = Yayasan::factory()->create();
    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);

    $this->actingAs($manager)->get(route('admin.tahun-ajaran.index'))
        ->assertOk()
        ->assertViewHas('isYayasan', true)
        ->assertViewHas('activeLembaga', null);
});

it('passes isYayasan=true and activeLembaga=<lembaga aktif> when a lembaga is switched into', function () {
    $permissions = ['tahun-ajaran.view', 'tahun-ajaran.create', 'tahun-ajaran.activate', 'semester.create', 'semester.activate'];
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo($permissions);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);
    session(['active_lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($manager)->get(route('admin.tahun-ajaran.index'))->assertOk();
    $response->assertViewHas('isYayasan', true);
    $response->assertViewHas('activeLembaga', fn ($activeLembaga) => $activeLembaga->id === $lembaga->id);
});

it('passes isYayasan=false to the index view for a lembaga-scoped actor', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsTahunAjaranManager($lembaga);

    $this->actingAs($manager)->get(route('admin.tahun-ajaran.index'))
        ->assertOk()
        ->assertViewHas('isYayasan', false);
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="passes isYayasan" --compact`
Expected: FAIL — `assertViewHas('isYayasan', ...)` tidak ketemu key `isYayasan` di view data (controller belum mengirimnya).

- [ ] **Step 3: Implementasi minimal**

Di `app/Http/Controllers/Admin/TahunAjaranController.php`, tambah import:

```php
use App\Models\Lembaga;
```

(sisipkan setelah `use App\Models\TahunAjaran;`, urutan alfabetis).

Ganti method `index()`:

```php
public function index(): View
{
    $this->authorize('tahun-ajaran.view');

    return view('admin.tahun-ajaran.index', [
        'tahunAjaranList' => TahunAjaran::with('semester')->get(),
    ]);
}
```

menjadi:

```php
public function index(Request $request): View
{
    $this->authorize('tahun-ajaran.view');

    return view('admin.tahun-ajaran.index', [
        'tahunAjaranList' => TahunAjaran::with(['semester', 'lembaga'])->get(),
        ...$this->scopeHeaderData($request),
    ]);
}

/**
 * Info scope yayasan/lembaga yang sedang aktif, ditampilkan sebagai badge di header
 * halaman (pola sama seperti admin/siswa/index.blade.php) -- HANYA relevan untuk aktor
 * berscope yayasan (punya switcher lembaga).
 *
 * @return array{isYayasan: bool, activeLembaga: ?Lembaga}
 */
private function scopeHeaderData(Request $request): array
{
    $isYayasan = $request->user()->widestScopeLevel() === 'yayasan';
    $lembagaId = $this->resolveActiveLembagaId($request->user());

    return [
        'isYayasan' => $isYayasan,
        'activeLembaga' => ($isYayasan && $lembagaId) ? Lembaga::withoutGlobalScopes()->find($lembagaId) : null,
    ];
}
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="passes isYayasan" --compact`
Expected: PASS (3 test)

- [ ] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/TahunAjaranSemesterPanelTest.php --compact`
Expected: PASS semua (test lama + 3 test baru), karena `create()`/`store()`/`activate()` tidak diubah di task ini.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/TahunAjaranController.php tests/Feature/Admin/TahunAjaranSemesterPanelTest.php
git commit -m "feat(tahun-ajaran): tambah scopeHeaderData() untuk badge scope yayasan/lembaga"
```

---

### Task 2: View Index — Badge Header, Label per Kartu, Wording Dialog Aktivasi

**Files:**
- Modify: `resources/views/admin/tahun-ajaran/index.blade.php`
- Test: `tests/Feature/Admin/TahunAjaranSemesterPanelTest.php`

**Interfaces:**
- Consumes: `$isYayasan` (bool), `$activeLembaga` (`?Lembaga`) dari Task 1's `scopeHeaderData()`. `$ta->lembaga` (relasi eager-loaded dari Task 1's `with(['semester', 'lembaga'])`).

- [ ] **Step 1: Tulis test yang gagal — badge header**

Tambahkan ke `tests/Feature/Admin/TahunAjaranSemesterPanelTest.php`:

```php
it('shows the "Semua Lembaga" badge in the index header for a yayasan-scoped actor in aggregate mode', function () {
    $permissions = ['tahun-ajaran.view', 'tahun-ajaran.create', 'tahun-ajaran.activate', 'semester.create', 'semester.activate'];
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo($permissions);

    $yayasan = Yayasan::factory()->create();
    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);

    $this->actingAs($manager)->get(route('admin.tahun-ajaran.index'))->assertSee('Semua Lembaga');
});

it('shows the active lembaga name badge instead of "Semua Lembaga" when a lembaga is switched into', function () {
    $permissions = ['tahun-ajaran.view', 'tahun-ajaran.create', 'tahun-ajaran.activate', 'semester.create', 'semester.activate'];
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo($permissions);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SD Teladan Satu']);
    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);
    session(['active_lembaga_id' => $lembaga->id]);

    $this->actingAs($manager)->get(route('admin.tahun-ajaran.index'))->assertSee('SD Teladan Satu');
});

it('does not show any scope badge in the index header for a lembaga-scoped actor', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsTahunAjaranManager($lembaga);

    $this->actingAs($manager)->get(route('admin.tahun-ajaran.index'))->assertDontSee('Semua Lembaga');
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="scope badge" --compact`
Expected: FAIL — 2 test pertama gagal (`assertSee` tidak ketemu teks badge), test ketiga (`assertDontSee`) langsung PASS (badge memang belum ada sama sekali) — INI NORMAL, jangan khawatir; fokus ke 2 test pertama yang harus gagal dulu.

- [ ] **Step 3: Implementasi minimal — badge header**

Di `resources/views/admin/tahun-ajaran/index.blade.php`, ganti (baris ±88-92):

```blade
        {{-- Page Header --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-400">Data Induk</p>
                <h1 class="mt-0.5 font-display text-xl font-bold tracking-tight text-gray-900">Tahun Ajaran &amp; Semester</h1>
            </div>
```

menjadi:

```blade
        {{-- Page Header --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-400">Data Induk</p>
                <div class="mt-0.5 flex flex-wrap items-center gap-2.5">
                    <h1 class="font-display text-xl font-bold tracking-tight text-gray-900">Tahun Ajaran &amp; Semester</h1>
                    @if ($isYayasan ?? (auth()->user()?->widestScopeLevel() === 'yayasan'))
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ ($activeLembaga ?? null) ? 'border border-brand-200 bg-brand-50 text-brand-700' : 'border border-purple-200 bg-purple-50 text-purple-700' }}">
                            <x-icon name="apartment" class="h-3.5 w-3.5" />
                            {{ ($activeLembaga ?? null) ? $activeLembaga->nama : 'Semua Lembaga' }}
                        </span>
                    @endif
                </div>
            </div>
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="scope badge" --compact`
Expected: PASS semua 3 test.

- [ ] **Step 5: Tulis test yang gagal — label lembaga per kartu**

Tambahkan:

```php
it('shows each lembaga name label on cards in aggregate mode when 2 lembaga share the same tahun ajaran name', function () {
    $permissions = ['tahun-ajaran.view', 'tahun-ajaran.create', 'tahun-ajaran.activate', 'semester.create', 'semester.activate'];
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo($permissions);

    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SD Cendekia Utama']);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SMP Cendekia Utama']);
    TahunAjaran::create(['lembaga_id' => $lembagaA->id, 'nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30']);
    TahunAjaran::create(['lembaga_id' => $lembagaB->id, 'nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30']);

    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);

    $response = $this->actingAs($manager)->get(route('admin.tahun-ajaran.index'))->assertOk();
    $response->assertSee('SD Cendekia Utama');
    $response->assertSee('SMP Cendekia Utama');
});

it('hides the lembaga name label on cards once a lembaga is switched into', function () {
    $permissions = ['tahun-ajaran.view', 'tahun-ajaran.create', 'tahun-ajaran.activate', 'semester.create', 'semester.activate'];
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo($permissions);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SD Cendekia Dua']);
    TahunAjaran::create(['lembaga_id' => $lembaga->id, 'nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30']);

    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);
    session(['active_lembaga_id' => $lembaga->id]);

    // "SD Cendekia Dua" TETAP muncul (dari badge header Task 2 Step 3), tapi HANYA
    // 1 kali (dari header) -- kalau label per-kartu ikut muncul, akan ada 2 kemunculan.
    $response = $this->actingAs($manager)->get(route('admin.tahun-ajaran.index'))->assertOk();
    expect(substr_count($response->getContent(), 'SD Cendekia Dua'))->toBe(1);
});
```

- [ ] **Step 6: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="lembaga name label" --compact`
Expected: FAIL — test pertama gagal (label belum ada), test kedua kemungkinan PASS kebetulan (badge header saja = 1 kemunculan, sama seperti target) — cek manual, kalau sudah PASS di step ini lanjut saja, itu tidak masalah (assertion-nya tetap valid untuk regresi setelah Step 7).

- [ ] **Step 7: Implementasi minimal — label lembaga per kartu**

Di file yang sama, ganti (baris ±113-118, bagian "Top Bar Card"):

```blade
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="font-display text-lg font-bold text-gray-900">{{ $ta->nama }}</h3>
                                    @if ($isTaActive)
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700 border border-emerald-200 shadow-2xs">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                            Aktif
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-[11px] font-medium text-gray-600">
                                            Non-aktif
                                        </span>
                                    @endif
                                </div>
                                <p class="mt-1.5 flex items-center gap-1.5 text-xs font-medium text-gray-500">
                                    <x-icon name="event" class="h-3.5 w-3.5 text-gray-400" />
                                    <span>{{ \Carbon\Carbon::parse($ta->tanggal_mulai)->translatedFormat('d M Y') }} - {{ \Carbon\Carbon::parse($ta->tanggal_selesai)->translatedFormat('d M Y') }}</span>
                                </p>
                            </div>
```

menjadi:

```blade
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="font-display text-lg font-bold text-gray-900">{{ $ta->nama }}</h3>
                                    @if ($isTaActive)
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700 border border-emerald-200 shadow-2xs">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                            Aktif
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-[11px] font-medium text-gray-600">
                                            Non-aktif
                                        </span>
                                    @endif
                                </div>
                                <p class="mt-1.5 flex items-center gap-1.5 text-xs font-medium text-gray-500">
                                    <x-icon name="event" class="h-3.5 w-3.5 text-gray-400" />
                                    <span>{{ \Carbon\Carbon::parse($ta->tanggal_mulai)->translatedFormat('d M Y') }} - {{ \Carbon\Carbon::parse($ta->tanggal_selesai)->translatedFormat('d M Y') }}</span>
                                </p>
                                @if (($isYayasan ?? false) && ! ($activeLembaga ?? null))
                                    <p class="mt-1 flex items-center gap-1.5 text-[11px] font-medium text-gray-400">
                                        <x-icon name="apartment" class="h-3 w-3" />
                                        <span>{{ $ta->lembaga->nama ?? '-' }}</span>
                                    </p>
                                @endif
                            </div>
```

(Indentasi disesuaikan dengan level nesting file asli — cek indentasi persis di file sebelum menempel, ini contoh dengan indentasi 28 spasi mengikuti struktur `@forelse` di dalamnya.)

- [ ] **Step 8: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="lembaga name label" --compact`
Expected: PASS semua 2 test.

- [ ] **Step 9: Tulis test yang gagal — wording dialog aktivasi**

Tambahkan:

```php
it('mentions the owning lembaga name in the activation confirm dialog wording', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SMA Bina Insan']);
    $manager = actingAsTahunAjaranManager($lembaga);
    $this->actingAs($manager);

    TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => false,
    ]);

    $this->get(route('admin.tahun-ajaran.index'))
        ->assertSee('Tahun Ajaran lain di lembaga SMA Bina Insan akan dinonaktifkan', false);
});
```

- [ ] **Step 10: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="activation confirm dialog wording" --compact`
Expected: FAIL — teks lama belum menyebut nama lembaga.

- [ ] **Step 11: Implementasi minimal — wording dialog**

Di file yang sama, ganti (baris ±141):

```blade
                                            <form action="{{ route('admin.tahun-ajaran.activate', $ta) }}" method="POST" class="inline" onsubmit="return confirm('Aktifkan Tahun Ajaran ini? Tahun Ajaran lain akan dinonaktifkan.')">
```

menjadi:

```blade
                                            <form action="{{ route('admin.tahun-ajaran.activate', $ta) }}" method="POST" class="inline" onsubmit="return confirm('Aktifkan {{ $ta->nama }}? Tahun Ajaran lain di lembaga {{ $ta->lembaga->nama ?? 'ini' }} akan dinonaktifkan (tidak memengaruhi lembaga lain).')">
```

- [ ] **Step 12: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="activation confirm dialog wording" --compact`
Expected: PASS.

- [ ] **Step 13: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/TahunAjaranSemesterPanelTest.php --compact`
Expected: PASS semua.

- [ ] **Step 14: Commit**

```bash
git add resources/views/admin/tahun-ajaran/index.blade.php tests/Feature/Admin/TahunAjaranSemesterPanelTest.php
git commit -m "feat(tahun-ajaran): badge scope, label lembaga per kartu, wording aktivasi diperjelas"
```

---

### Task 3: View Modal — Konteks Lembaga Tujuan + Fix Ikon `date_range`

**Files:**
- Modify: `resources/views/admin/tahun-ajaran/_modal-tahun-ajaran.blade.php`
- Test: `tests/Feature/Admin/TahunAjaranSemesterPanelTest.php`

**Interfaces:**
- Consumes: `$isYayasan`, `$activeLembaga` (sama seperti Task 2 — modal ini di-`@include` dari `index.blade.php` sehingga otomatis mewarisi variabel yang sama, tidak perlu passing manual).

- [ ] **Step 1: Tulis test yang gagal — konteks lembaga di modal + fix ikon**

Tambahkan:

```php
it('shows the target lembaga name inside the create/edit modal when a lembaga is switched into', function () {
    $permissions = ['tahun-ajaran.view', 'tahun-ajaran.create', 'tahun-ajaran.activate', 'semester.create', 'semester.activate'];
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo($permissions);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SD Nusantara Jaya']);
    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);
    session(['active_lembaga_id' => $lembaga->id]);

    $this->actingAs($manager)->get(route('admin.tahun-ajaran.index'))
        ->assertSee('Untuk lembaga: SD Nusantara Jaya');
});

it('shows a warning inside the modal when a yayasan-scoped actor has not switched into a lembaga yet', function () {
    $permissions = ['tahun-ajaran.view', 'tahun-ajaran.create', 'tahun-ajaran.activate', 'semester.create', 'semester.activate'];
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo($permissions);

    $yayasan = Yayasan::factory()->create();
    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);

    $this->actingAs($manager)->get(route('admin.tahun-ajaran.index'))
        ->assertSee('Pilih lembaga aktif dulu melalui pengalih lembaga di atas', false);
});

it('does not show any lembaga context text inside the modal for a lembaga-scoped actor', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsTahunAjaranManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.tahun-ajaran.index'))->assertOk();
    $response->assertDontSee('Untuk lembaga:');
    $response->assertDontSee('Pilih lembaga aktif dulu melalui pengalih lembaga di atas', false);
});

it('renders the calendar_month icon instead of the unknown-icon placeholder in the modal header', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsTahunAjaranManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.tahun-ajaran.index'))->assertOk();
    // Path unik ikon "default" (placeholder tanda tanya) di components/icon.blade.php --
    // TIDAK BOLEH muncul di halaman ini kalau ikon date_range sudah diganti.
    $response->assertDontSee('M9.5 9a2.5 2.5 0 0 1 4.6-1.4', false);
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="target lembaga name inside the create|shows a warning inside the modal|lembaga context text inside the modal|calendar_month icon" --compact`
Expected: FAIL — 3 test pertama gagal (teks konteks belum ada), test ke-4 (`calendar_month` icon) JUGA gagal karena saat ini `date_range` masih dipakai sehingga placeholder default (path `M9.5 9a2.5 2.5 0 0 1 4.6-1.4`) memang muncul di response.

- [ ] **Step 3: Implementasi minimal**

Di `resources/views/admin/tahun-ajaran/_modal-tahun-ajaran.blade.php`, ganti (baris 14-22):

```blade
        <div class="flex items-center justify-between pb-3.5 border-b border-gray-200">
            <h3 class="font-display text-base font-bold text-gray-900 flex items-center gap-2">
                <x-icon name="date_range" class="h-5 w-5 text-brand-500" />
                <span x-text="modalTahunAjaranMode === 'create' ? 'Tambah Tahun Ajaran' : 'Edit Tahun Ajaran'"></span>
            </h3>
            <button @click="showModalTahunAjaran = false" type="button" class="text-gray-400 hover:text-gray-600 transition">
                <x-icon name="cancel" class="h-5 w-5" />
            </button>
        </div>
```

menjadi:

```blade
        <div class="flex items-center justify-between pb-3.5 border-b border-gray-200">
            <div>
                <h3 class="font-display text-base font-bold text-gray-900 flex items-center gap-2">
                    <x-icon name="calendar_month" class="h-5 w-5 text-brand-500" />
                    <span x-text="modalTahunAjaranMode === 'create' ? 'Tambah Tahun Ajaran' : 'Edit Tahun Ajaran'"></span>
                </h3>
                @if ($isYayasan ?? false)
                    <p class="mt-1 pl-7 text-xs {{ ($activeLembaga ?? null) ? 'text-gray-500' : 'text-error-600 font-semibold' }}">
                        @if ($activeLembaga ?? null)
                            Untuk lembaga: {{ $activeLembaga->nama }}
                        @else
                            Pilih lembaga aktif dulu melalui pengalih lembaga di atas — tidak bisa menambah Tahun Ajaran saat mode "Semua Lembaga".
                        @endif
                    </p>
                @endif
            </div>
            <button @click="showModalTahunAjaran = false" type="button" class="text-gray-400 hover:text-gray-600 transition">
                <x-icon name="cancel" class="h-5 w-5" />
            </button>
        </div>
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="target lembaga name inside the create|shows a warning inside the modal|lembaga context text inside the modal|calendar_month icon" --compact`
Expected: PASS semua 4 test.

- [ ] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/TahunAjaranSemesterPanelTest.php --compact`
Expected: PASS semua.

- [ ] **Step 6: Commit**

```bash
git add resources/views/admin/tahun-ajaran/_modal-tahun-ajaran.blade.php tests/Feature/Admin/TahunAjaranSemesterPanelTest.php
git commit -m "feat(tahun-ajaran): konteks lembaga tujuan di modal + fix ikon date_range->calendar_month"
```

---

### Task 4: Hapus Halaman Mati `admin.tahun-ajaran.create`

**Files:**
- Modify: `routes/admin/akademik-master.php`
- Modify: `app/Http/Controllers/Admin/TahunAjaranController.php`
- Delete: `resources/views/admin/tahun-ajaran/create.blade.php`
- Test: `tests/Feature/Admin/TahunAjaranSemesterPanelTest.php`

**Interfaces:**
- Tidak ada interface baru — task ini murni penghapusan, tidak ada kode baru yang dikonsumsi task lain.

- [ ] **Step 1: Verifikasi ulang sebelum hapus (wajib, sesuai spec)**

Run: `php artisan route:list --name=tahun-ajaran`
Expected output mengandung baris `GET|HEAD admin/tahun-ajaran/create ... tahun-ajaran.create` (route masih ada, akan dihapus di Step 4) dan TIDAK ada route lain yang bergantung pada nama `tahun-ajaran.create`.

Cari referensi tersisa (harus 0 hasil selain baris route itu sendiri):

Run: `grep -rn "tahun-ajaran.create" resources/views app --include="*.php" --include="*.blade.php"`
Expected: hanya menunjukkan `routes/admin/akademik-master.php` (baris route) dan `app/Http/Controllers/Admin/TahunAjaranController.php` (method `create()` dan `@can`/permission check di `store()`/`update()` yang memakai NAMA PERMISSION `tahun-ajaran.create`, BUKAN nama route — permission ini TETAP DIPERTAHANKAN, jangan hapus). Kalau ada hasil lain di luar 2 file itu, STOP dan laporkan sebelum lanjut — berarti ada referensi yang terlewat di audit awal.

- [ ] **Step 2: Tulis test yang gagal — route sudah tidak ada**

Tambahkan ke `tests/Feature/Admin/TahunAjaranSemesterPanelTest.php`:

```php
it('no longer registers the dead admin.tahun-ajaran.create route', function () {
    expect(fn () => route('admin.tahun-ajaran.create'))
        ->toThrow(\Symfony\Component\Routing\Exception\RouteNotFoundException::class);
});
```

- [ ] **Step 3: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="dead admin.tahun-ajaran.create route" --compact`
Expected: FAIL — route masih terdaftar, `route()` berhasil resolve, tidak melempar exception.

- [ ] **Step 4: Implementasi — hapus route, method, file**

Di `routes/admin/akademik-master.php`, hapus baris:

```php
Route::get('tahun-ajaran/create', [TahunAjaranController::class, 'create'])->name('tahun-ajaran.create');
```

Di `app/Http/Controllers/Admin/TahunAjaranController.php`, hapus method:

```php
public function create(): View
{
    $this->authorize('tahun-ajaran.create');

    return view('admin.tahun-ajaran.create');
}
```

Hapus file `resources/views/admin/tahun-ajaran/create.blade.php` seluruhnya:

```bash
git rm resources/views/admin/tahun-ajaran/create.blade.php
```

- [ ] **Step 5: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="dead admin.tahun-ajaran.create route" --compact`
Expected: PASS.

- [ ] **Step 6: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/TahunAjaranSemesterPanelTest.php --compact`
Expected: PASS semua — khususnya test yang memanggil `route('admin.tahun-ajaran.store')`/`route('admin.tahun-ajaran.index')`/`route('admin.tahun-ajaran.activate', ...)` TETAP jalan normal (route-route itu TIDAK dihapus, hanya `tahun-ajaran.create`).

- [ ] **Step 7: Commit**

```bash
git add routes/admin/akademik-master.php app/Http/Controllers/Admin/TahunAjaranController.php tests/Feature/Admin/TahunAjaranSemesterPanelTest.php
git commit -m "chore(tahun-ajaran): hapus halaman create yang tidak pernah diakses dari UI manapun"
```

---

### Task 5: Penutup — Regresi Penuh & Pint

**Files:**
- Tidak ada file baru — task verifikasi murni.

- [ ] **Step 1: Jalankan seluruh test yang menyentuh modul Tahun Ajaran/Semester**

Run: `php artisan test --compact --filter="TahunAjaranSemesterPanelTest|TahunAjaranActivationTest|TahunAjaranSemesterFeatureTest|TahunAjaranSeederTest|SemesterActivationTest|SemesterSeederTest"`
Expected: PASS semua, 0 gagal.

- [ ] **Step 2: Jalankan Pint pada file yang diubah**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `{"tool":"pint","result":"passed"}` (atau auto-fix berhasil tanpa error).

- [ ] **Step 3: Verifikasi manual cepat via browser (opsional tapi disarankan)**

Buka `/admin/tahun-ajaran` sebagai user yayasan-scope: cek badge "Semua Lembaga" muncul di header, cek label nama lembaga muncul di tiap kartu, cek modal Tambah menunjukkan peringatan lembaga, cek ikon kalender modal tidak lagi tanda tanya. Switch ke 1 lembaga: cek badge berubah jadi nama lembaga, label per-kartu hilang, modal menunjukkan "Untuk lembaga: ...".

- [ ] **Step 4: Laporkan hasil**

TIDAK perlu menulis file handoff log baru di task ini — itu bagian dari kickoff terpisah yang akan ditulis manual setelah plan ini disetujui/dieksekusi (mengikuti pola proyek-proyek sebelumnya di sesi ini).

---

## Self-Review

**1. Spec coverage** — semua 6 item dari spec `.agents/specs/2026-09-08-tahun-ajaran-scope-wording-audit.md` tercakup: Item 1+2 di Task 1+2, Item 3 di Task 2, Item 4+6 di Task 3 (digabung sesuai instruksi spec), Item 5 di Task 4. "Di Luar Scope" spec (Semester::activate() scoping, disable tombol simpan, rombak SemesterController) sengaja TIDAK ada task-nya, sesuai spec.

**2. Placeholder scan** — tidak ada "TBD"/"tambahkan validasi"/dst. Semua step berisi kode lengkap siap tempel, path file, dan command persis.

**3. Type consistency** — `scopeHeaderData(Request $request): array` (Task 1) dipakai identik di Task 2/3 lewat variabel `$isYayasan`/`$activeLembaga` yang namanya konsisten di semua task. `resolveActiveLembagaId(User $actor): ?int` dipanggil dengan argumen `$request->user()` (tipe `User`, sesuai signature trait) di semua tempat.

**Catatan tambahan hasil self-review**: Task 2 Step 6 test kedua (`hides the lembaga name label...`) berpotensi PASS bahkan SEBELUM Step 7 diimplementasikan (karena baseline count sudah 1 dari badge header Task 2 Step 3) — ini didokumentasikan eksplisit di Step 6 supaya pelaksana tidak bingung kenapa 1 dari 2 test barunya sudah hijau lebih awal; tetap wajib lanjut ke Step 7 karena test PERTAMA (2 lembaga beda nama sama) tetap gagal sampai label diimplementasikan.
