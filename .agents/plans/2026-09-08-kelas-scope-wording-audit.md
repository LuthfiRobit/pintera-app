# Validasi Scope Backend & Kejujuran Wording — Menu Kelas Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tutup 3 gap backend (guard `create()`, scoping eksplisit dropdown `create()`/`edit()`) dan 3 gap frontend (badge scope, kolom Lembaga di tabel, label filter) di menu "Kelas" — tanpa mengubah `CreateKelasAction`/`UpdateKelasAction` yang sudah aman.

**Architecture:** Perubahan di `KelasController` (guard + scoping eksplisit + helper `scopeHeaderData()` — pola identik Karyawan/Guru/TahunAjaran sesi ini) dan 2 view file (`index.blade.php`, `_daftar.blade.php`, `create.blade.php`, `edit.blade.php`). `CreateKelasAction`/`UpdateKelasAction` TIDAK disentuh sama sekali.

**Tech Stack:** Laravel 12, Blade, Pest (function-style test).

## Global Constraints

- `CreateKelasAction`/`UpdateKelasAction` TIDAK BOLEH diubah — keduanya sudah benar (404 kalau kombinasi lembaga tidak cocok).
- `resolveActiveLembagaId(User $actor)` dari `ResolveLembagaScopeTrait` dipakai untuk guard A.1, scoping A.2, dan `scopeHeaderData()` — method ini menangani yayasan-scope (baca session) MAUPUN lembaga-scope (`$actor->lembaga_id` langsung) dalam 1 pemanggilan.
- `edit()` (A.3) WAJIB pakai `$kelas->lembaga_id` (properti record), BUKAN `resolveActiveLembagaId()` — target lembaga saat edit adalah lembaga PEMILIK kelas, bukan lembaga switcher aktor.
- Badge di halaman **create** (B.1) SELALU brand color dengan nama lembaga — TIDAK PERNAH varian ungu "Semua Lembaga" (guard A.1 menjamin `$activeLembaga` tidak null di titik itu).
- Badge di halaman **edit** (B.1) pakai `$kelas->lembaga->nama`, BUKAN `$activeLembaga` — konsisten dengan A.3.
- Kolom "Lembaga" (B.2) dan label filter (B.3) HANYA muncul saat `($isYayasan ?? false) && ! ($activeLembaga ?? null)` (mode "Semua Lembaga") — disembunyikan di semua kondisi lain.
- `colspan` baris empty-state tabel (B.2) WAJIB dihitung dinamis (4 atau 5), tidak boleh di-hardcode.

---

## Konteks File yang Sudah Ada (baca sebelum mulai)

- `app/Http/Controllers/Admin/KelasController.php` — sudah `use ResolveLembagaScopeTrait;`, `use AuthorizesRequests;`. BELUM `use App\Models\Lembaga;`, `use App\Models\Scopes\TenantScope;`, `use Illuminate\Http\RedirectResponse;`.
- `tests/Feature/Admin/KelasCrudTest.php` — Pest function-style, sudah punya helper `actingAsKelasManager(Lembaga $lembaga): User` (lembaga-scope actor, permission `kelas.view/create/edit`). Sudah ADA test "offers only guru belonging to the current lembaga as wali kelas options" (baris 48-70) — TAPI test itu cuma menguji aktor LEMBAGA-SCOPE (kebetulan lolos lewat `TenantScope` ambien, BUKAN scoping eksplisit A.2). Semua test baru plan ini ditambahkan ke file yang sama, dengan setup MANUAL untuk aktor yayasan-scope (ikuti pola test "menolak actor yayasan dengan active_lembaga_id stale..." baris 214-236 di file yang sama sebagai referensi gaya).
- `resources/views/admin/kelas/index.blade.php`, `_daftar.blade.php` (AJAX partial), `create.blade.php`, `edit.blade.php`, `_form.blade.php` — `_form.blade.php` TIDAK disentuh plan ini (dropdown-nya otomatis benar begitu controller sudah scoped, tidak perlu ubah markup form itu sendiri).
- `Fase` model TIDAK pakai `BelongsToTenant` (referensi kurikulum nasional) — TIDAK pernah di-scope ke lembaga di mana pun dalam plan ini.

---

### Task 1: Controller — `scopeHeaderData()`, Guard `create()`, Scoping Dropdown `create()`

**Files:**
- Modify: `app/Http/Controllers/Admin/KelasController.php`
- Test: `tests/Feature/Admin/KelasCrudTest.php`

**Interfaces:**
- Produces: `KelasController::scopeHeaderData(Request $request): array` → `['isYayasan' => bool, 'activeLembaga' => ?Lembaga]`, dipakai Task 2 dan Task 3.
- Produces: `create()` signature berubah jadi `create(Request $request): View|RedirectResponse`.

- [ ] **Step 1: Tulis test yang gagal — guard create() tanpa lembaga aktif**

Tambahkan ke `tests/Feature/Admin/KelasCrudTest.php` (di akhir file):

```php
it('redirects back with an error when a yayasan-scoped actor opens create without an active lembaga', function () {
    Permission::firstOrCreate(['name' => 'kelas.create', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['kelas.create']);

    $yayasan = Yayasan::factory()->create();
    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);

    $this->actingAs($manager)->get(route('admin.kelas.create'))
        ->assertRedirect(route('admin.kelas.index'))
        ->assertSessionHasErrors('lembaga_id');
});

it('shows the create form when a yayasan-scoped actor has switched into a lembaga', function () {
    Permission::firstOrCreate(['name' => 'kelas.create', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['kelas.create']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);
    session(['active_lembaga_id' => $lembaga->id]);

    $this->actingAs($manager)->get(route('admin.kelas.create'))->assertOk();
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="opens create without an active lembaga|switched into a lembaga" --compact`
Expected: FAIL — test pertama gagal (`create()` saat ini SELALU `assertOk()`, tidak pernah redirect); test kedua kemungkinan sudah PASS kebetulan (form memang render tanpa error saat ini) — fokus ke test pertama.

- [ ] **Step 3: Tulis test yang gagal — scoping dropdown create()**

Tambahkan:

```php
it('only offers tahun ajaran belonging to the active lembaga in the create dropdown for a yayasan-scoped actor', function () {
    Permission::firstOrCreate(['name' => 'kelas.create', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['kelas.create']);

    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $taA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id, 'nama' => '2026/2027 A']);
    TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id, 'nama' => '2026/2027 B']);

    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);
    session(['active_lembaga_id' => $lembagaA->id]);

    $response = $this->actingAs($manager)->get(route('admin.kelas.create'))->assertOk();
    $response->assertViewHas('tahunAjaranList', function ($list) use ($taA) {
        return $list->count() === 1 && $list->first()->id === $taA->id;
    });
});
```

- [ ] **Step 4: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="only offers tahun ajaran belonging to the active lembaga" --compact`
Expected: FAIL — `tahunAjaranList` saat ini berisi TA dari KEDUA lembaga (ambien `TenantScope` untuk yayasan-scope dengan lembaga aktif SEBENARNYA sudah memfilter ke 1 lembaga — cek dulu apakah test ini kebetulan sudah PASS; kalau PASS, lanjut ke Step 5 tanpa khawatir, karena Step 5 tetap membuat kode LEBIH EKSPLISIT walau hasilnya sama untuk kasus lembaga aktif sudah dipilih — nilai baru scoping eksplisit terlihat di Task lain/kasus edge, bukan di test ini).

- [ ] **Step 5: Implementasi minimal**

Di `app/Http/Controllers/Admin/KelasController.php`, tambah import (urutan alfabetis, sisipkan di antara import yang sudah ada):

```php
use App\Models\Lembaga;
use App\Models\Scopes\TenantScope;
```

dan:

```php
use Illuminate\Http\RedirectResponse;
```

(cek `Illuminate\Http\RedirectResponse` — kemungkinan SUDAH ada karena dipakai `store()`/`update()`; kalau sudah ada, JANGAN duplikasi import).

Ganti method `create()`:

```php
public function create(): View
{
    $this->authorize('kelas.create');

    return view('admin.kelas.create', [
        'tahunAjaranList' => TahunAjaran::orderByDesc('tanggal_mulai')->get(),
        'guruList' => Guru::with('person')->orderByNama()->get(),
        'polaJamList' => PolaJam::orderBy('nama')->get(),
        'faseList' => Fase::orderBy('urutan')->get(),
    ]);
}
```

menjadi:

```php
public function create(Request $request): View|RedirectResponse
{
    $this->authorize('kelas.create');

    $lembagaId = $this->resolveActiveLembagaId($request->user());
    if ($lembagaId === null) {
        return redirect()->route('admin.kelas.index')
            ->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah kelas.']);
    }

    return view('admin.kelas.create', [
        'tahunAjaranList' => TahunAjaran::withoutGlobalScope(TenantScope::class)->where('lembaga_id', $lembagaId)->orderByDesc('tanggal_mulai')->get(),
        'guruList' => Guru::withoutGlobalScope(TenantScope::class)->where('lembaga_id', $lembagaId)->with('person')->orderByNama()->get(),
        'polaJamList' => PolaJam::withoutGlobalScope(TenantScope::class)->where('lembaga_id', $lembagaId)->orderBy('nama')->get(),
        'faseList' => Fase::orderBy('urutan')->get(),
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

- [ ] **Step 6: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="opens create without an active lembaga|switched into a lembaga|only offers tahun ajaran belonging to the active lembaga" --compact`
Expected: PASS semua 3 test.

- [ ] **Step 7: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/KelasCrudTest.php --compact`
Expected: PASS semua — termasuk test lama "offers only guru belonging to the current lembaga as wali kelas options" (baris 48-70) yang sekarang MELEWATI JALUR SCOPING EKSPLISIT baru (bukan lagi cuma kebetulan lolos via TenantScope ambien) karena aktor test itu lembaga-scope dan `resolveActiveLembagaId()` mengembalikan `$actor->lembaga_id` langsung untuknya.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Admin/KelasController.php tests/Feature/Admin/KelasCrudTest.php
git commit -m "feat(kelas): guard create() + scoping eksplisit dropdown ke lembaga aktif"
```

---

### Task 2: Controller — Scoping Dropdown `edit()` ke Lembaga Pemilik Kelas

**Files:**
- Modify: `app/Http/Controllers/Admin/KelasController.php`
- Test: `tests/Feature/Admin/KelasCrudTest.php`

**Interfaces:**
- Consumes: `scopeHeaderData()` dari Task 1.

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('only offers tahun ajaran and guru belonging to the kelas lembaga in the edit dropdown, even in "Semua Lembaga" mode', function () {
    Permission::firstOrCreate(['name' => 'kelas.edit', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['kelas.edit']);

    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $taA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id, 'nama' => '2026/2027 A']);
    TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id, 'nama' => '2026/2027 B']);
    $guruA = Guru::factory()->create(['lembaga_id' => $lembagaA->id]);
    Guru::factory()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembagaB->id])->id,
        'lembaga_id' => $lembagaB->id,
        'nik' => '3201234567894444',
        'nama' => 'Guru Lembaga B Edit',
        'jenis_kelamin' => 'L',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY',
    ]);
    $kelas = Kelas::create(['lembaga_id' => $lembagaA->id, 'tahun_ajaran_id' => $taA->id, 'nama' => '6A']);

    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);
    // TIDAK switch ke lembaga manapun -- mode "Semua Lembaga" aktif.

    $response = $this->actingAs($manager)->get(route('admin.kelas.edit', $kelas))->assertOk();
    $response->assertViewHas('tahunAjaranList', fn ($list) => $list->count() === 1 && $list->first()->id === $taA->id);
    $response->assertViewHas('guruList', fn ($list) => $list->count() === 1 && $list->first()->id === $guruA->id);
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="Semua Lembaga.*mode" --compact`
Expected: FAIL — `tahunAjaranList`/`guruList` saat ini AGREGAT (berisi opsi dari Lembaga A DAN B, karena `TenantScope` untuk yayasan-scope tanpa lembaga aktif mengagregasi seluruh yayasan).

- [ ] **Step 3: Implementasi minimal**

Ganti method `edit()`:

```php
public function edit(Kelas $kelas): View
{
    $this->authorize('kelas.edit');

    return view('admin.kelas.edit', [
        'kelas' => $kelas,
        'tahunAjaranList' => TahunAjaran::orderByDesc('tanggal_mulai')->get(),
        'guruList' => Guru::with('person')->orderByNama()->get(),
        'polaJamList' => PolaJam::orderBy('nama')->get(),
        'faseList' => Fase::orderBy('urutan')->get(),
    ]);
}
```

menjadi:

```php
public function edit(Request $request, Kelas $kelas): View
{
    $this->authorize('kelas.edit');

    return view('admin.kelas.edit', [
        'kelas' => $kelas,
        'tahunAjaranList' => TahunAjaran::withoutGlobalScope(TenantScope::class)->where('lembaga_id', $kelas->lembaga_id)->orderByDesc('tanggal_mulai')->get(),
        'guruList' => Guru::withoutGlobalScope(TenantScope::class)->where('lembaga_id', $kelas->lembaga_id)->with('person')->orderByNama()->get(),
        'polaJamList' => PolaJam::withoutGlobalScope(TenantScope::class)->where('lembaga_id', $kelas->lembaga_id)->orderBy('nama')->get(),
        'faseList' => Fase::orderBy('urutan')->get(),
        ...$this->scopeHeaderData($request),
    ]);
}
```

(`Request $request` ditambahkan ke signature — dibutuhkan `scopeHeaderData()` untuk Task 3's badge edit; `$kelas` tetap route-model-binding, urutan parameter `Request` sebelum model binding konsisten dengan pola `KaryawanController`/`GuruController` sesi ini).

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="Semua Lembaga.*mode" --compact`
Expected: PASS.

- [ ] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/KelasCrudTest.php --compact`
Expected: PASS semua.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/KelasController.php tests/Feature/Admin/KelasCrudTest.php
git commit -m "feat(kelas): scoping eksplisit dropdown edit() ke lembaga pemilik kelas"
```

---

### Task 3: Controller — Wiring `index()` (scopeHeaderData + Eager Load untuk Badge/Kolom/Filter)

**Files:**
- Modify: `app/Http/Controllers/Admin/KelasController.php`
- Test: `tests/Feature/Admin/KelasCrudTest.php`

**Interfaces:**
- Consumes: `scopeHeaderData()` dari Task 1.
- Produces: view `admin.kelas.index` dan `admin.kelas._daftar` (KEDUA cabang ajax/non-ajax) menerima `$isYayasan`/`$activeLembaga`; `$kelasList` items punya relasi `lembaga` ter-eager-load; `$tahunAjaranList` (filter dropdown) punya relasi `lembaga` ter-eager-load.

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('passes isYayasan and activeLembaga to both the full index page and the ajax partial', function () {
    Permission::firstOrCreate(['name' => 'kelas.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['kelas.view']);

    $yayasan = Yayasan::factory()->create();
    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);
    $this->actingAs($manager);

    $this->get(route('admin.kelas.index'))->assertOk()
        ->assertViewHas('isYayasan', true)
        ->assertViewHas('activeLembaga', null);

    $this->get(route('admin.kelas.index'), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
        ->assertViewHas('isYayasan', true)
        ->assertViewHas('activeLembaga', null);
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="both the full index page and the ajax partial" --compact`
Expected: FAIL — kedua cabang `index()` belum mengirim `isYayasan`/`activeLembaga`.

- [ ] **Step 3: Implementasi minimal**

Ganti method `index()`:

```php
public function index(Request $request): View
{
    $this->authorize('kelas.view');

    $perPage = in_array((int) $request->input('per_page'), [10, 25, 50]) ? (int) $request->input('per_page') : 20;

    $query = Kelas::with(['tahunAjaran', 'waliKelas'])->orderBy('nama');

    if ($search = $request->input('search')) {
        $query->where('nama', 'like', '%'.$search.'%');
    }

    if ($tahunAjaranId = $request->input('tahun_ajaran_id')) {
        $query->where('tahun_ajaran_id', $tahunAjaranId);
    }

    $kelasList = $query->paginate($perPage)->withQueryString();

    if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
        return view('admin.kelas._daftar', [
            'kelasList' => $kelasList,
            'perPage' => $perPage,
        ]);
    }

    return view('admin.kelas.index', [
        'kelasList' => $kelasList,
        'tahunAjaranList' => TahunAjaran::orderByDesc('tanggal_mulai')->get(),
        'perPage' => $perPage,
        'totalKelas' => Kelas::count(),
        'totalTaAktif' => Kelas::whereHas('tahunAjaran', fn ($q) => $q->where('status_aktif', true))->count(),
    ]);
}
```

menjadi:

```php
public function index(Request $request): View
{
    $this->authorize('kelas.view');

    $perPage = in_array((int) $request->input('per_page'), [10, 25, 50]) ? (int) $request->input('per_page') : 20;

    $query = Kelas::with(['tahunAjaran', 'waliKelas', 'lembaga'])->orderBy('nama');

    if ($search = $request->input('search')) {
        $query->where('nama', 'like', '%'.$search.'%');
    }

    if ($tahunAjaranId = $request->input('tahun_ajaran_id')) {
        $query->where('tahun_ajaran_id', $tahunAjaranId);
    }

    $kelasList = $query->paginate($perPage)->withQueryString();

    if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
        return view('admin.kelas._daftar', [
            'kelasList' => $kelasList,
            'perPage' => $perPage,
            ...$this->scopeHeaderData($request),
        ]);
    }

    return view('admin.kelas.index', [
        'kelasList' => $kelasList,
        'tahunAjaranList' => TahunAjaran::with('lembaga')->orderByDesc('tanggal_mulai')->get(),
        'perPage' => $perPage,
        'totalKelas' => Kelas::count(),
        'totalTaAktif' => Kelas::whereHas('tahunAjaran', fn ($q) => $q->where('status_aktif', true))->count(),
        ...$this->scopeHeaderData($request),
    ]);
}
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="both the full index page and the ajax partial" --compact`
Expected: PASS.

- [ ] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/KelasCrudTest.php --compact`
Expected: PASS semua.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/KelasController.php tests/Feature/Admin/KelasCrudTest.php
git commit -m "feat(kelas): wiring scopeHeaderData() + eager-load lembaga di index()"
```

---

### Task 4: View — Badge Scope di Index/Create/Edit

**Files:**
- Modify: `resources/views/admin/kelas/index.blade.php`
- Modify: `resources/views/admin/kelas/create.blade.php`
- Modify: `resources/views/admin/kelas/edit.blade.php`
- Test: `tests/Feature/Admin/KelasCrudTest.php`

**Interfaces:**
- Consumes: `$isYayasan`, `$activeLembaga` dari Task 1/2/3.

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('shows the "Semua Lembaga" badge on the kelas index for a yayasan-scoped actor in aggregate mode', function () {
    Permission::firstOrCreate(['name' => 'kelas.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['kelas.view']);

    $yayasan = Yayasan::factory()->create();
    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);

    $this->actingAs($manager)->get(route('admin.kelas.index'))->assertSee('Semua Lembaga');
});

it('shows the switched lembaga name badge on the create page (never the "Semua Lembaga" variant)', function () {
    Permission::firstOrCreate(['name' => 'kelas.create', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['kelas.create']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SD Cakrawala Ilmu']);
    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);
    session(['active_lembaga_id' => $lembaga->id]);

    $this->actingAs($manager)->get(route('admin.kelas.create'))
        ->assertSee('SD Cakrawala Ilmu')
        ->assertDontSee('Semua Lembaga');
});

it('shows the owning lembaga name badge on the edit page even in "Semua Lembaga" mode', function () {
    Permission::firstOrCreate(['name' => 'kelas.edit', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['kelas.edit']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SMP Cakrawala Ilmu']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => '7A']);

    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);
    // TIDAK switch lembaga -- mode "Semua Lembaga".

    $this->actingAs($manager)->get(route('admin.kelas.edit', $kelas))->assertSee('SMP Cakrawala Ilmu');
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="Semua Lembaga.*badge|switched lembaga name badge|owning lembaga name badge" --compact`
Expected: FAIL — 3 test gagal, badge belum ada di ketiga halaman.

- [ ] **Step 3: Implementasi minimal — index**

Di `resources/views/admin/kelas/index.blade.php`, ganti (baris ±12-19):

```blade
        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Kelas</h1>
                <p class="text-xs text-gray-500 mt-0.5">Kelola daftar kelas, penugasan wali kelas, dan ikatan tahun ajaran lembaga.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Kelas</b>
            </p>
        </div>
```

menjadi:

```blade
        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="font-display text-lg font-bold text-gray-900">Kelas</h1>
                    @if ($isYayasan ?? (auth()->user()?->widestScopeLevel() === 'yayasan'))
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ ($activeLembaga ?? null) ? 'border border-brand-200 bg-brand-50 text-brand-700' : 'border border-purple-200 bg-purple-50 text-purple-700' }}">
                            <x-icon name="apartment" class="h-3.5 w-3.5" />
                            {{ ($activeLembaga ?? null) ? $activeLembaga->nama : 'Semua Lembaga' }}
                        </span>
                    @endif
                </div>
                <p class="text-xs text-gray-500 mt-0.5">Kelola daftar kelas, penugasan wali kelas, dan ikatan tahun ajaran lembaga.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Kelas</b>
            </p>
        </div>
```

- [ ] **Step 4: Implementasi minimal — create**

Di `resources/views/admin/kelas/create.blade.php`, ganti (baris ±12-13):

```blade
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Tambah Kelas</h1>
```

menjadi:

```blade
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="font-display text-lg font-bold text-gray-900">Tambah Kelas</h1>
                @if ($isYayasan ?? false)
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-brand-200 bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-700">
                        <x-icon name="apartment" class="h-3.5 w-3.5" />
                        {{ $activeLembaga->nama }}
                    </span>
                @endif
            </div>
```

- [ ] **Step 5: Implementasi minimal — edit**

Di `resources/views/admin/kelas/edit.blade.php`, ganti (baris ±12-13):

```blade
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Edit Kelas: {{ $kelas->nama }}</h1>
```

menjadi:

```blade
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="font-display text-lg font-bold text-gray-900">Edit Kelas: {{ $kelas->nama }}</h1>
                @if ($isYayasan ?? false)
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-brand-200 bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-700">
                        <x-icon name="apartment" class="h-3.5 w-3.5" />
                        {{ $kelas->lembaga->nama }}
                    </span>
                @endif
            </div>
```

- [ ] **Step 6: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="Semua Lembaga.*badge|switched lembaga name badge|owning lembaga name badge" --compact`
Expected: PASS semua 3 test.

- [ ] **Step 7: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/KelasCrudTest.php --compact`
Expected: PASS semua.

- [ ] **Step 8: Commit**

```bash
git add resources/views/admin/kelas/index.blade.php resources/views/admin/kelas/create.blade.php resources/views/admin/kelas/edit.blade.php tests/Feature/Admin/KelasCrudTest.php
git commit -m "feat(kelas): badge scope yayasan/lembaga di index, create, edit"
```

---

### Task 5: View — Kolom "Lembaga" di Tabel + Label Filter Tahun Ajaran

**Files:**
- Modify: `resources/views/admin/kelas/_daftar.blade.php`
- Modify: `resources/views/admin/kelas/index.blade.php`
- Test: `tests/Feature/Admin/KelasCrudTest.php`

**Interfaces:**
- Consumes: `$isYayasan`, `$activeLembaga` (Task 3), `$kelas->lembaga` dan `$ta->lembaga` (eager-loaded Task 3).

- [ ] **Step 1: Tulis test yang gagal — kolom Lembaga**

Tambahkan:

```php
it('shows a Lembaga column with each lembaga name in the kelas table during aggregate mode', function () {
    Permission::firstOrCreate(['name' => 'kelas.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['kelas.view']);

    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SD Melati Satu']);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SD Melati Dua']);
    $taA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $taB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    Kelas::create(['lembaga_id' => $lembagaA->id, 'tahun_ajaran_id' => $taA->id, 'nama' => '1A']);
    Kelas::create(['lembaga_id' => $lembagaB->id, 'tahun_ajaran_id' => $taB->id, 'nama' => '1A']);

    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);

    $response = $this->actingAs($manager)->get(route('admin.kelas.index'))->assertOk();
    $response->assertSee('SD Melati Satu');
    $response->assertSee('SD Melati Dua');
});

it('hides the Lembaga column once a lembaga is switched into', function () {
    Permission::firstOrCreate(['name' => 'kelas.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['kelas.view']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    Kelas::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => '1A']);

    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);
    session(['active_lembaga_id' => $lembaga->id]);

    $this->actingAs($manager)->get(route('admin.kelas.index'))->assertDontSee('>Lembaga<', false);
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="Lembaga column" --compact`
Expected: FAIL — test pertama gagal (kolom belum ada), test kedua kemungkinan sudah PASS kebetulan (kolom memang belum ada sama sekali di kondisi manapun) — normal, fokus ke test pertama.

- [ ] **Step 3: Implementasi minimal — kolom tabel**

Di `resources/views/admin/kelas/_daftar.blade.php`, ganti header tabel (baris 24-32):

```blade
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                    <th class="sticky left-0 z-10 bg-white px-5 py-3">Aksi</th>
                    <th class="px-5 py-3">Nama Kelas</th>
                    <th class="px-5 py-3">Tahun Ajaran</th>
                    <th class="px-5 py-3">Wali Kelas</th>
                </tr>
            </thead>
```

menjadi:

```blade
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                    <th class="sticky left-0 z-10 bg-white px-5 py-3">Aksi</th>
                    <th class="px-5 py-3">Nama Kelas</th>
                    @if (($isYayasan ?? false) && ! ($activeLembaga ?? null))
                        <th class="px-5 py-3">Lembaga</th>
                    @endif
                    <th class="px-5 py-3">Tahun Ajaran</th>
                    <th class="px-5 py-3">Wali Kelas</th>
                </tr>
            </thead>
```

Ganti body tabel (baris 33-78):

```blade
            <tbody class="divide-y divide-gray-100">
                @foreach ($kelasList as $kelas)
                    <tr class="transition hover:bg-gray-50">
                        <td class="sticky left-0 z-10 bg-white px-5 py-3">
                            <x-table-actions>
                                <x-dropdown-link :href="route('admin.kelas.edit', $kelas)">
                                    <span class="inline-flex items-center gap-2.5">
                                        <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                                        Edit Kelas
                                    </span>
                                </x-dropdown-link>
                            </x-table-actions>
                        </td>
                        <td class="px-5 py-3.5 font-semibold text-gray-900">
                            {{ $kelas->nama }}
                            @if ($kelas->tingkat)
                                <span class="ml-1 text-xs font-normal text-gray-400">(Tingkat {{ $kelas->tingkat }})</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-gray-600">
                            {{ $kelas->tahunAjaran->nama }}
                            @if ($kelas->tahunAjaran->status_aktif)
                                <x-badge tone="green">Aktif</x-badge>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-gray-600">
                            @if ($kelas->waliKelas)
                                {{ $kelas->waliKelas->nama }}
                            @else
                                <x-badge tone="slate">Belum ditentukan</x-badge>
                            @endif
                        </td>
                    </tr>
                @endforeach

                @if ($kelasList->isEmpty())
                    <tr>
                        <td colspan="4" class="px-5 py-10 text-center text-gray-500">
                            @if (request()->anyFilled(['search', 'tahun_ajaran_id']))
                                Tidak ada kelas yang cocok dengan filter ini.
                            @else
                                Belum ada kelas yang didaftarkan.
                            @endif
                        </td>
                    </tr>
                @endif
            </tbody>
```

menjadi:

```blade
            <tbody class="divide-y divide-gray-100">
                @foreach ($kelasList as $kelas)
                    <tr class="transition hover:bg-gray-50">
                        <td class="sticky left-0 z-10 bg-white px-5 py-3">
                            <x-table-actions>
                                <x-dropdown-link :href="route('admin.kelas.edit', $kelas)">
                                    <span class="inline-flex items-center gap-2.5">
                                        <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                                        Edit Kelas
                                    </span>
                                </x-dropdown-link>
                            </x-table-actions>
                        </td>
                        <td class="px-5 py-3.5 font-semibold text-gray-900">
                            {{ $kelas->nama }}
                            @if ($kelas->tingkat)
                                <span class="ml-1 text-xs font-normal text-gray-400">(Tingkat {{ $kelas->tingkat }})</span>
                            @endif
                        </td>
                        @if (($isYayasan ?? false) && ! ($activeLembaga ?? null))
                            <td class="px-5 py-3.5 text-gray-500">{{ $kelas->lembaga->nama ?? '-' }}</td>
                        @endif
                        <td class="px-5 py-3.5 text-gray-600">
                            {{ $kelas->tahunAjaran->nama }}
                            @if ($kelas->tahunAjaran->status_aktif)
                                <x-badge tone="green">Aktif</x-badge>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-gray-600">
                            @if ($kelas->waliKelas)
                                {{ $kelas->waliKelas->nama }}
                            @else
                                <x-badge tone="slate">Belum ditentukan</x-badge>
                            @endif
                        </td>
                    </tr>
                @endforeach

                @if ($kelasList->isEmpty())
                    <tr>
                        <td colspan="{{ ($isYayasan ?? false) && ! ($activeLembaga ?? null) ? 5 : 4 }}" class="px-5 py-10 text-center text-gray-500">
                            @if (request()->anyFilled(['search', 'tahun_ajaran_id']))
                                Tidak ada kelas yang cocok dengan filter ini.
                            @else
                                Belum ada kelas yang didaftarkan.
                            @endif
                        </td>
                    </tr>
                @endif
            </tbody>
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="Lembaga column" --compact`
Expected: PASS semua 2 test.

- [ ] **Step 5: Tulis test yang gagal — label filter dropdown**

Tambahkan:

```php
it('shows the lembaga name suffix in the Tahun Ajaran filter dropdown during aggregate mode', function () {
    Permission::firstOrCreate(['name' => 'kelas.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['kelas.view']);

    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SMA Pelita Bangsa']);
    TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id, 'nama' => '2026/2027']);

    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);

    $this->actingAs($manager)->get(route('admin.kelas.index'))
        ->assertSee('2026/2027 — SMA Pelita Bangsa', false);
});
```

- [ ] **Step 6: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="lembaga name suffix in the Tahun Ajaran filter" --compact`
Expected: FAIL — teks label lembaga belum ada di option filter.

- [ ] **Step 7: Implementasi minimal — label filter**

Di `resources/views/admin/kelas/index.blade.php`, ganti (baris ±90-97):

```blade
                        <select x-ref="taSelect" x-init="initFilterSelect($refs.taSelect, 'tahun_ajaran_id', true)" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                            <option value="">Semua Tahun Ajaran</option>
                            @foreach ($tahunAjaranList as $ta)
                                <option value="{{ $ta->id }}" @selected(request('tahun_ajaran_id') == $ta->id)>
                                    {{ $ta->nama }}{{ $ta->status_aktif ? ' (Aktif)' : '' }}
                                </option>
                            @endforeach
                        </select>
```

menjadi:

```blade
                        <select x-ref="taSelect" x-init="initFilterSelect($refs.taSelect, 'tahun_ajaran_id', true)" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                            <option value="">Semua Tahun Ajaran</option>
                            @foreach ($tahunAjaranList as $ta)
                                <option value="{{ $ta->id }}" @selected(request('tahun_ajaran_id') == $ta->id)>
                                    {{ $ta->nama }}{{ $ta->status_aktif ? ' (Aktif)' : '' }}{{ ($isYayasan ?? false) && ! ($activeLembaga ?? null) ? ' — '.($ta->lembaga->nama ?? '-') : '' }}
                                </option>
                            @endforeach
                        </select>
```

- [ ] **Step 8: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="lembaga name suffix in the Tahun Ajaran filter" --compact`
Expected: PASS.

- [ ] **Step 9: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/KelasCrudTest.php --compact`
Expected: PASS semua.

- [ ] **Step 10: Commit**

```bash
git add resources/views/admin/kelas/_daftar.blade.php resources/views/admin/kelas/index.blade.php tests/Feature/Admin/KelasCrudTest.php
git commit -m "feat(kelas): kolom Lembaga di tabel + label lembaga di filter Tahun Ajaran"
```

---

### Task 6: Penutup — Regresi Penuh & Pint

**Files:**
- Tidak ada file baru — task verifikasi murni.

- [ ] **Step 1: Jalankan seluruh test yang menyentuh Kelas**

Run: `php artisan test --compact --filter="KelasCrudTest|KelasTest|KelasPolaJamTest|KelasFaseAssignmentTest|KelasFaseSuggestionTest|KelasKurikulumSnapshotTest|CreateKelasActionTest|UpdateKelasActionTest|KelasSeederTest"`
Expected: PASS semua, 0 gagal.

- [ ] **Step 2: Jalankan Pint pada file yang diubah**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `{"tool":"pint","result":"passed"}`.

- [ ] **Step 3: Verifikasi manual cepat via browser (opsional tapi disarankan)**

Login sebagai yayasan-scope dalam mode "Semua Lembaga": buka `/admin/kelas`, cek badge "Semua Lembaga", cek kolom "Lembaga" di tabel, cek filter Tahun Ajaran menampilkan nama lembaga. Klik "Tambah Kelas" TANPA switch lembaga dulu → harus redirect balik ke index dengan pesan error. Switch ke 1 lembaga, klik "Tambah Kelas" lagi → form terbuka normal, badge selalu warna brand dengan nama lembaga, dropdown Tahun Ajaran/Wali Kelas/Pola Jam HANYA berisi opsi lembaga itu. Buka edit kelas milik lembaga LAIN (switch balik ke "Semua Lembaga" dulu) → badge edit menunjukkan nama lembaga PEMILIK kelas, dropdown-nya juga cuma opsi lembaga itu.

- [ ] **Step 4: Laporkan hasil**

TIDAK perlu menulis file handoff log baru di task ini (sama seperti plan Tahun Ajaran) — kalau user menghendaki log terpisah, itu permintaan tambahan setelah plan ini selesai.

---

## Self-Review

**1. Spec coverage** — semua 6 item spec `.agents/specs/2026-09-08-kelas-scope-wording-audit.md` tercakup: A.1+A.2 di Task 1, A.3 di Task 2, B.1 (index bagian data) di Task 3 lalu badge markup di Task 4, B.2+B.3 di Task 5. "Di Luar Scope" spec (FormRequest `exists:` rule, filter guru per jenis_ptk, data legacy waliKelas/polaJam) sengaja tidak ada task-nya.

**2. Placeholder scan** — tidak ada "TBD"/dst. Semua step berisi kode lengkap.

**3. Type consistency** — `scopeHeaderData(Request $request): array` didefinisikan Task 1, dipanggil identik di Task 2 (`edit()`, dengan `Request $request` baru ditambahkan ke signature) dan Task 3 (`index()`, sudah punya `Request $request` dari awal). Variabel `$isYayasan`/`$activeLembaga` dipakai konsisten namanya di Task 4/5's Blade.

**Catatan tambahan hasil self-review**:
- Task 1 Step 4 dan Task 5 Step 2/6 masing-masing punya 1 test yang berpotensi PASS lebih awal dari yang diharapkan (dijelaskan eksplisit di step itu sendiri) — bukan indikasi kesalahan, murni karena sebagian perilaku sudah kebetulan benar lewat `TenantScope` ambien untuk kasus tertentu (lembaga sudah di-switch). Pelaksana TIDAK PERLU khawatir, tetap lanjut ke step implementasi berikutnya.
- Task 2 mengubah signature `edit()` jadi `edit(Request $request, Kelas $kelas)` — WAJIB urutan `Request` SEBELUM model binding (`Kelas $kelas`), konsisten Laravel route-model-binding dan pola `KaryawanController::edit()`/`GuruController::edit()` sesi ini.
